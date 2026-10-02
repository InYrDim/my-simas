<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantUrl;
use Modules\Ppdb\App\Domain\Actions\DeletePath;
use Modules\Ppdb\App\Domain\Actions\DeleteWave;
use Modules\Ppdb\App\Domain\Actions\SavePaths;
use Modules\Ppdb\App\Domain\Actions\SavePeriod;
use Modules\Ppdb\App\Domain\Actions\SaveWave;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Queries\ChosenPeriod;
use Modules\Ppdb\App\Domain\Support\SchoolDay;
use Modules\Ppdb\App\Http\Requests\PathsRequest;
use Modules\Ppdb\App\Http\Requests\PeriodRequest;
use Modules\Ppdb\App\Http\Requests\WaveRequest;

/**
 * Pengaturan PPDB: the periods of the school, the waves and the paths with
 * their quota of the chosen period, and the code and link the school hands
 * to applicants.
 */
final class SettingsController
{
    public function show(Request $request, ChosenPeriod $chosen, SchoolDay $day, TenantContext $context, TenantUrl $urls): Response
    {
        $periods = $chosen->all();
        $selected = $chosen->among($periods, $request->integer('periode'));

        $today = $day->today();
        $tenant = $context->currentOrFail();

        return Inertia::render('Ppdb/Settings', [
            'periods' => $periods->map(fn (AdmissionPeriod $period): array => $this->periodProps($period))->values()->all(),
            'selected' => $selected === null ? null : $this->periodProps($selected),
            'waves' => $selected === null ? [] : $selected->waves->map(fn (AdmissionWave $wave): array => [
                'id' => $wave->id,
                'name' => $wave->name,
                'opensOn' => $wave->opens_on,
                'closesOn' => $wave->closes_on,
                'opensLabel' => $day->label($wave->opens_on),
                'closesLabel' => $day->label($wave->closes_on),
                'status' => $wave->statusOn($today),
            ])->values()->all(),
            'paths' => $selected === null ? [] : $selected->paths->map(fn (AdmissionPath $path): array => [
                'id' => $path->id,
                'name' => $path->name,
                'quota' => $path->quota,
            ])->values()->all(),
            'statuses' => array_map(
                fn (PeriodStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                PeriodStatus::cases(),
            ),
            'school' => [
                'name' => $tenant->name,
                'code' => $tenant->id,
                'joinUrl' => $urls->url($tenant->id, '/calon-siswa/gabung'),
            ],
        ]);
    }

    public function storePeriod(PeriodRequest $request, SavePeriod $save): RedirectResponse
    {
        $period = $save->handle(null, $request->periodData());

        return redirect()
            ->route('ppdb.settings', ['periode' => $period->id])
            ->with('status', 'Periode PPDB dibuat.');
    }

    public function updatePeriod(PeriodRequest $request, AdmissionPeriod $period, SavePeriod $save): RedirectResponse
    {
        $save->handle($period, $request->periodData());

        return back()->with('status', 'Periode PPDB disimpan.');
    }

    public function storeWave(WaveRequest $request, AdmissionPeriod $period, SaveWave $save): RedirectResponse
    {
        $save->handle($period, null, $request->waveData());

        return back()->with('status', 'Gelombang ditambahkan.');
    }

    public function updateWave(WaveRequest $request, AdmissionWave $wave, SaveWave $save): RedirectResponse
    {
        $period = AdmissionPeriod::query()->findOrFail($wave->period_id);

        $save->handle($period, $wave, $request->waveData());

        return back()->with('status', 'Gelombang disimpan.');
    }

    public function destroyWave(AdmissionWave $wave, DeleteWave $delete): RedirectResponse
    {
        $delete->handle($wave);

        return back()->with('status', 'Gelombang dihapus.');
    }

    public function updatePaths(PathsRequest $request, AdmissionPeriod $period, SavePaths $save): RedirectResponse
    {
        /** @var list<array{id?: int|null, name: string, quota: int}> $paths */
        $paths = array_values($request->validated('paths'));

        $save->handle($period, $paths);

        return back()->with('status', 'Jalur dan kuota disimpan.');
    }

    public function destroyPath(AdmissionPath $path, DeletePath $delete): RedirectResponse
    {
        $delete->handle($path);

        return back()->with('status', 'Jalur dihapus.');
    }

    /**
     * @return array{id: int, name: string, entryYear: int, status: string, statusLabel: string, resultsPublished: bool}
     */
    private function periodProps(AdmissionPeriod $period): array
    {
        return [
            'id' => $period->id,
            'name' => $period->name,
            'entryYear' => $period->entry_year,
            'status' => $period->status->value,
            'statusLabel' => $period->status->label(),
            'resultsPublished' => $period->results_published_at !== null,
        ];
    }
}
