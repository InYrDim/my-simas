<?php

namespace Modules\Ppdb\App\Domain\Reports;

use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\Report;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Hasil Seleksi PPDB: the verified applicants of the academic year path by
 * path, highest score first, with the committee's decision and whether the
 * applicant has re-registered. Those without a score come last in a path.
 */
final class SelectionResultReport implements Report
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'ppdb-result',
            group: 'Penerimaan (PPDB)',
            name: 'Hasil Seleksi PPDB',
            description: 'Peringkat, keputusan, dan daftar cadangan.',
            permission: 'ppdb.view',
            order: 80,
        );
    }

    public function table(ReportPeriod $period): ReportTable
    {
        $paths = AdmissionPath::query()->orderBy('period_id')->orderBy('sort_order')->orderBy('id')->get();

        $applicants = Applicant::query()
            ->whereBetween('registered_on', [$period->startsOn, $period->endsOn])
            ->where('status', 'verified')
            ->orderByRaw('score is null')
            ->orderByDesc('score')
            ->orderBy('number')
            ->get()
            ->groupBy('path_id');

        $rows = [];

        foreach ($paths as $path) {
            $rank = 0;

            foreach ($applicants->get($path->id, []) as $applicant) {
                $rows[] = [
                    $path->name,
                    $applicant->score === null ? null : ++$rank,
                    $applicant->number,
                    $applicant->name,
                    $applicant->score === null ? null : number_format((float) $applicant->score, 2, '.', ''),
                    $applicant->decision->label(),
                    $applicant->isEnrolled() ? 'Sudah' : 'Belum',
                ];
            }
        }

        return new ReportTable(
            'Hasil Seleksi PPDB',
            ['Jalur', 'Peringkat', 'No. Daftar', 'Nama', 'Nilai', 'Keputusan', 'Daftar Ulang'],
            $rows,
        );
    }
}
