<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Queries\AdmissionFunnel;
use Modules\Ppdb\App\Domain\Support\SchoolDay;

/**
 * Ringkasan PPDB: the running period, its admissions funnel and its
 * registration waves.
 */
final class OverviewController
{
    public function __invoke(SchoolDay $day, AdmissionFunnel $funnel): Response
    {
        $period = AdmissionPeriod::active();
        $waves = $period->waves ?? collect();
        $perWave = $period === null ? [] : $funnel->perWave($period);
        $today = $day->today();
        $lastDay = $waves->max('closes_on');

        return Inertia::render('Ppdb/Overview', [
            'period' => $period === null ? null : [
                'id' => $period->id,
                'name' => $period->name,
                'status' => $period->status->value,
                'closesOn' => $lastDay === null ? null : $day->label($lastDay),
            ],
            'funnel' => $period === null ? [] : $funnel->forPeriod($period),
            'waves' => $waves->map(fn (AdmissionWave $wave): array => [
                'id' => $wave->id,
                'name' => $wave->name,
                'opensOn' => $day->label($wave->opens_on),
                'closesOn' => $day->label($wave->closes_on),
                'status' => $wave->statusOn($today),
                'applicants' => $perWave[$wave->id] ?? 0,
            ])->values()->all(),
            'can' => ['manageSettings' => Gate::allows('ppdb.settings.manage')],
        ]);
    }
}
