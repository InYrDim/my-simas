<?php

namespace Modules\Attendance\App\Domain\Queries;

use Carbon\CarbonImmutable;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * One month of one class: each active student's days per status and the
 * share of recorded days the student was at school.
 */
final class MonthlyRecap
{
    public function __construct(
        private readonly StudentDirectory $students,
    ) {}

    /**
     * @param  string  $month  Y-m
     * @return list<array{id: int, name: string, nis: string, present: int, late: int, sick: int, permit: int, absent: int, days: int, percent: ?int}>
     */
    public function forClass(int $classId, string $month): array
    {
        $students = $this->students->ofClass($classId);
        $first = CarbonImmutable::parse("{$month}-01");

        /** @var array<int, AttendanceTally> $tallies */
        $tallies = [];

        DailyAttendance::query()
            ->whereIn('student_id', array_column($students, 'id'))
            ->whereBetween('date', [$first->toDateString(), $first->endOfMonth()->toDateString()])
            ->get(['student_id', 'status'])
            ->each(function (DailyAttendance $row) use (&$tallies): void {
                ($tallies[$row->student_id] ??= new AttendanceTally)->add($row->status);
            });

        $rows = [];

        foreach ($students as $student) {
            $tally = $tallies[$student->id] ?? new AttendanceTally;

            $rows[] = [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                ...$tally->toArray(),
                'days' => $tally->total(),
                'percent' => $tally->percent(),
            ];
        }

        return $rows;
    }
}
