<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;

/**
 * Creates or updates a period. A new period gets the usual admission paths
 * (without seats: the school sets the quota). Making a period active closes
 * the one that was active, so a school never has two.
 */
final class SavePeriod
{
    /**
     * @var list<string>
     */
    public const DEFAULT_PATHS = ['Zonasi', 'Prestasi', 'Afirmasi', 'Mutasi'];

    /**
     * @param  array{name: string, entry_year: int, status: string}  $data
     *
     * @throws ValidationException when the status cannot be set
     */
    public function handle(?AdmissionPeriod $period, array $data): AdmissionPeriod
    {
        $isNew = $period === null;
        $period ??= new AdmissionPeriod;
        $status = PeriodStatus::from($data['status']);

        if ($period->results_published_at !== null && $status === PeriodStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Periode yang hasilnya sudah diumumkan tidak bisa dikembalikan menjadi konsep.',
            ]);
        }

        return DB::transaction(function () use ($period, $data, $status, $isNew): AdmissionPeriod {
            $period->fill([
                'name' => $data['name'],
                'entry_year' => $data['entry_year'],
                'status' => $status,
            ])->save();

            if ($isNew) {
                foreach (self::DEFAULT_PATHS as $index => $name) {
                    AdmissionPath::query()->create([
                        'period_id' => $period->id,
                        'name' => $name,
                        'quota' => 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            if ($status === PeriodStatus::Active) {
                AdmissionPeriod::query()
                    ->whereKeyNot($period->id)
                    ->where('status', PeriodStatus::Active->value)
                    ->update(['status' => PeriodStatus::Closed->value]);
            }

            return $period;
        });
    }
}
