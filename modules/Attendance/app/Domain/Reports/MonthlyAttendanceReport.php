<?php

namespace Modules\Attendance\App\Domain\Reports;

use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Queries\AttendanceTally;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\Report;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * Rekap Kehadiran Bulanan: every student's days per status, month by
 * month, within the academic year. Read from the daily records, with the
 * class each record was written for. Months are grouped here, not in SQL,
 * so every database gives the same table.
 */
final class MonthlyAttendanceReport implements Report
{
    public function __construct(
        private readonly StudentDirectory $students,
        private readonly ClassDirectory $classes,
        private readonly SchoolClock $clock,
    ) {}

    public function definition(): ReportDefinition
    {
        return new ReportDefinition(
            key: 'attendance-monthly',
            group: 'Kehadiran',
            name: 'Rekap Kehadiran Bulanan',
            description: 'Hadir, terlambat, sakit, izin, dan alpa setiap siswa per bulan.',
            permission: 'attendance.view',
            order: 50,
        );
    }

    public function table(ReportPeriod $period): ReportTable
    {
        /** @var array<string, array{month: string, classId: int, studentId: int, tally: AttendanceTally}> $groups */
        $groups = [];

        DailyAttendance::query()
            ->whereBetween('date', [$period->startsOn, $period->endsOn])
            ->get(['student_id', 'class_id', 'date', 'status'])
            ->each(function (DailyAttendance $row) use (&$groups): void {
                $month = substr($row->date, 0, 7);
                $key = "{$month}|{$row->class_id}|{$row->student_id}";

                $groups[$key] ??= ['month' => $month, 'classId' => $row->class_id, 'studentId' => $row->student_id, 'tally' => new AttendanceTally];
                $groups[$key]['tally']->add($row->status);
            });

        $students = $this->students->many(array_values(array_unique(array_column($groups, 'studentId'))));
        $classNames = array_column($this->classes->ofYear($period->academicYearId), 'name', 'id');

        $rows = [];

        foreach ($groups as $group) {
            $student = $students[$group['studentId']] ?? null;
            $tally = $group['tally'];

            $rows[] = [
                'month' => $group['month'],
                'class' => $classNames[$group['classId']] ?? '-',
                'name' => $student->name ?? '(siswa dihapus)',
                'cells' => [
                    $this->clock->monthLabel($group['month']),
                    $classNames[$group['classId']] ?? '-',
                    $student->nis ?? '',
                    $student->name ?? '(siswa dihapus)',
                    ...array_values($tally->toArray()),
                    $tally->percent(),
                ],
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['month'], $b['month'])
            ?: strnatcasecmp($a['class'], $b['class'])
            ?: strcasecmp($a['name'], $b['name']));

        return new ReportTable(
            'Rekap Kehadiran Bulanan',
            ['Bulan', 'Kelas', 'NIS', 'Nama', 'Hadir', 'Terlambat', 'Sakit', 'Izin', 'Alpa', 'Kehadiran (%)'],
            array_column($rows, 'cells'),
        );
    }
}
