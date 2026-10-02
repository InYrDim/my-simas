<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Saves the paths a page sends for one period: a row with an id updates
 * that path, a row without one adds a path, and the order of the rows is
 * the order of the paths. A path left out of the list is untouched — a
 * path is removed by its own action, once nothing depends on it. Names are
 * unique within the period.
 */
final class SavePaths
{
    /**
     * @param  list<array{id?: int|null, name: string, quota: int}>  $paths
     *
     * @throws ValidationException when a name repeats or an id is not a path of this period
     */
    public function handle(AdmissionPeriod $period, array $paths): void
    {
        /** @var Collection<int, AdmissionPath> $existing */
        $existing = $period->paths()->get()->keyBy('id');
        $sentIds = collect($paths)->pluck('id')->filter()->map(fn ($id): int => (int) $id)->all();

        $taken = $existing
            ->reject(fn (AdmissionPath $path): bool => in_array($path->id, $sentIds, true))
            ->map(fn (AdmissionPath $path): string => mb_strtolower($path->name))
            ->values()
            ->all();

        foreach ($paths as $index => $row) {
            $id = $row['id'] ?? null;

            if ($id !== null && ! $existing->has((int) $id)) {
                throw ValidationException::withMessages(["paths.{$index}.id" => 'Jalur tidak ditemukan.']);
            }

            $key = mb_strtolower(trim($row['name']));

            if (in_array($key, $taken, true)) {
                throw ValidationException::withMessages(["paths.{$index}.name" => 'Nama jalur tidak boleh ganda.']);
            }

            $taken[] = $key;

            if ($id !== null) {
                $accepted = Applicant::query()->where('path_id', (int) $id)->where('decision', Decision::Accepted->value)->count();

                if ($row['quota'] < $accepted) {
                    throw ValidationException::withMessages([
                        "paths.{$index}.quota" => "Kuota tidak boleh di bawah jumlah yang sudah diterima ({$accepted}).",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($period, $paths, $existing): void {
            foreach ($paths as $index => $row) {
                $attributes = ['name' => trim($row['name']), 'quota' => $row['quota'], 'sort_order' => $index];
                $id = $row['id'] ?? null;

                if ($id === null) {
                    AdmissionPath::query()->create([...$attributes, 'period_id' => $period->id]);

                    continue;
                }

                $existing->get((int) $id)->fill($attributes)->save();
            }
        });
    }
}
