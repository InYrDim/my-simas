<?php

namespace Modules\Core\App\Domain\Reports;

use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\Report;
use Modules\Core\App\Domain\Models\StudentClassHistory;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Mutasi Siswa: the students whose place changed during the academic
 * year — moved to another class, graduated, transferred or left. These
 * are the class-history rows SaveStudent marked with anything but the
 * plain "Kelas aktif"; the date is the day the change was recorded, on
 * the school's own clock. New admissions are not recorded anywhere, so
 * they are not part of this report.
 */
final class StudentMutationReport implements Report
{
    private const UNCHANGED_NOTE = 'Kelas aktif';

    public function __construct(private readonly TenantContext $context) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'student-mutation',
            group: 'Kesiswaan',
            name: 'Mutasi Siswa',
            description: 'Siswa yang pindah kelas, lulus, pindah sekolah, atau keluar pada tahun ajaran terpilih.',
            permission: 'core.master.view',
            order: 20,
        );
    }

    public function table(ReportPeriod $period): ReportTable
    {
        $timezone = $this->context->currentOrFail()->timezone;

        $rows = StudentClassHistory::query()
            ->where('academic_year_id', $period->academicYearId)
            ->where('note', '!=', self::UNCHANGED_NOTE)
            ->whereHas('student')
            ->with('student')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get()
            ->map(fn (StudentClassHistory $row): array => [
                $row->student->nis,
                $row->student->name,
                $row->class_name,
                $row->note,
                $row->updated_at?->timezone($timezone)->format('d/m/Y'),
            ])
            ->all();

        return new ReportTable('Mutasi Siswa', ['NIS', 'Nama', 'Kelas', 'Keterangan', 'Tanggal'], array_values($rows));
    }
}
