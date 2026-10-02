<?php

namespace Modules\Ppdb\App\Domain\Statistics;

use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\StatFigure;
use Modules\Core\App\Contracts\DTOs\StatPanel;
use Modules\Core\App\Contracts\StatisticsProvider;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Queries\ChosenPeriod;

/**
 * Admissions on the Statistik page: how many applicants the running
 * period has (the newest period when none is running) and how they split
 * over its paths. It is about the admissions period, not the academic
 * year the page is asked for.
 */
final class AdmissionStatistics implements StatisticsProvider
{
    public function __construct(
        private readonly ChosenPeriod $chosen,
    ) {}

    public function figures(?ReportPeriod $period): array
    {
        $admissions = $this->chosen->forRequest();

        return [
            new StatFigure(
                'ppdb-applicants-total',
                'Pendaftar PPDB',
                $admissions === null ? '—' : Applicant::query()->where('period_id', $admissions->id)->count(),
                $admissions === null ? 'Belum ada periode PPDB' : $admissions->name,
            ),
        ];
    }

    public function panels(?ReportPeriod $period): array
    {
        $admissions = $this->chosen->forRequest();

        if ($admissions === null) {
            return [];
        }

        $perPath = Applicant::query()
            ->where('period_id', $admissions->id)
            ->selectRaw('path_id, count(*) as total')
            ->groupBy('path_id')
            ->toBase()
            ->pluck('total', 'path_id');

        return [
            new StatPanel(
                key: 'ppdb-by-path',
                title: 'Pendaftar PPDB menurut jalur',
                kind: StatPanel::KIND_BARS,
                points: array_values($admissions->paths->map(fn (AdmissionPath $path): array => [
                    'label' => $path->name,
                    'value' => (int) ($perPath[$path->id] ?? 0),
                ])->all()),
                note: $admissions->name,
            ),
        ];
    }
}
