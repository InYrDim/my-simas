<?php

namespace Modules\Core\App\Domain\Reports;

use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\Report;
use Modules\Core\App\Domain\Models\StudentClassHistory;

/**
 * Daftar Siswa per Kelas: every student placed in a class during the
 * academic year, by class. Read from the class history, so a year that is
 * over still lists the classes as they were.
 */
final class StudentListReport implements Report
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'student-list',
            group: 'Kesiswaan',
            name: 'Daftar Siswa per Kelas',
            description: 'Siswa tiap rombel beserta NIS, NISN, dan jenis kelamin.',
            permission: 'core.master.view',
            order: 10,
        );
    }

    public function table(ReportPeriod $period): ReportTable
    {
        $rows = StudentClassHistory::query()
            ->where('academic_year_id', $period->academicYearId)
            ->whereHas('student')
            ->with('student')
            ->get()
            ->sort(fn (StudentClassHistory $a, StudentClassHistory $b): int => strnatcasecmp($a->class_name, $b->class_name)
                ?: strcasecmp($a->student->name, $b->student->name))
            ->map(fn (StudentClassHistory $row): array => [
                $row->class_name,
                $row->student->nis,
                $row->student->nisn,
                $row->student->name,
                $row->student->gender,
                $row->note,
            ])
            ->all();

        return new ReportTable('Daftar Siswa per Kelas', ['Kelas', 'NIS', 'NISN', 'Nama', 'L/P', 'Keterangan'], array_values($rows));
    }
}
