<?php

namespace Modules\Attendance\App\Domain\Reports;

use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Queries\AttendanceTally;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\Report;

/**
 * Kehadiran per Kelas: every class of the academic year with its daily
 * records per status and the share that was at school.
 */
final class ClassAttendanceReport implements Report
{
    public function __construct(
        private readonly ClassDirectory $classes,
    ) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'attendance-class',
            group: 'Kehadiran',
            name: 'Kehadiran per Kelas',
            description: 'Persentase kehadiran tiap kelas dalam satu tahun ajaran.',
            permission: 'attendance.view',
            order: 60,
        );
    }

    public function table(ReportPeriod $period): ReportTable
    {
        $classes = $this->classes->ofYear($period->academicYearId);

        /** @var array<int, AttendanceTally> $tallies */
        $tallies = [];

        DailyAttendance::query()
            ->whereBetween('date', [$period->startsOn, $period->endsOn])
            ->whereIn('class_id', array_column($classes, 'id'))
            ->get(['class_id', 'status'])
            ->each(function (DailyAttendance $row) use (&$tallies): void {
                ($tallies[$row->class_id] ??= new AttendanceTally)->add($row->status);
            });

        $rows = [];

        foreach ($classes as $class) {
            $tally = $tallies[$class->id] ?? new AttendanceTally;

            $rows[] = [
                $class->name,
                $class->homeroomName,
                $tally->total(),
                ...array_values($tally->toArray()),
                $tally->percent(),
            ];
        }

        return new ReportTable(
            'Kehadiran per Kelas',
            ['Kelas', 'Wali Kelas', 'Catatan', 'Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', 'Kehadiran (%)'],
            $rows,
        );
    }
}
