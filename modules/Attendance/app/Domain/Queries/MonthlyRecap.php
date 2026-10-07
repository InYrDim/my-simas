<?php

namespace Modules\Attendance\App\Domain\Queries;

use Carbon\CarbonImmutable;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * One month of one class: each active student's records per status and the
 * share of records the student was at school. Counts either the gate's
 * daily records, the lesson records, or both added together.
 */
final class MonthlyRecap
{
    public const GATE = 'gerbang';

    public const LESSONS = 'pelajaran';

    public const ALL = 'semua';

    public function __construct(
        private readonly StudentDirectory $students,
    ) {}

    /**
     * @param  string  $month  Y-m
     * @param  string  $source  GATE, LESSONS or ALL (both added together)
     * @return list<array{id: int, name: string, nis: string, present: int, late: int, sick: int, permit: int, absent: int, days: int, percent: ?int}>
     */
    public function forClass(int $classId, string $month, string $source = self::ALL): array
    {
        $students = $this->students->ofClass($classId);
        $first = CarbonImmutable::parse("{$month}-01");
        $period = [$first->toDateString(), $first->endOfMonth()->toDateString()];
        $studentIds = array_column($students, 'id');

        /** @var array<int, AttendanceTally> $tallies */
        $tallies = [];

        $count = function (DailyAttendance|LessonAttendance $row) use (&$tallies): void {
            ($tallies[$row->student_id] ??= new AttendanceTally)->add($row->status);
        };

        if ($source !== self::LESSONS) {
            DailyAttendance::query()
                ->whereIn('student_id', $studentIds)
                ->whereBetween('date', $period)
                ->get(['student_id', 'status'])
                ->each($count);
        }

        if ($source !== self::GATE) {
            LessonAttendance::query()
                ->whereIn('student_id', $studentIds)
                ->whereIn('lesson_session_id', LessonSession::query()
                    ->where('class_id', $classId)
                    ->whereBetween('date', $period)
                    ->select('id'))
                ->get(['student_id', 'status'])
                ->each($count);
        }

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
