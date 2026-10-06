<?php

namespace Modules\Attendance\App\Domain\Queries;

use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Core\App\Contracts\StudentDirectory;

/**
 * The roll of one class in one lesson of one day: every active student
 * with the status saved in the session. Before a session exists, a
 * student who is sick, excused or absent for the day shows that status;
 * everyone else is not marked yet.
 */
final class LessonRoll
{
    public function __construct(
        private readonly StudentDirectory $students,
    ) {}

    /**
     * @return list<array{id: int, name: string, nis: string, status: ?string, scanned: bool, daily: ?string}>
     */
    public function forClass(int $classId, string $date, ?LessonSession $session): array
    {
        $members = $this->students->ofClass($classId);
        $ids = array_column($members, 'id');

        $daily = DailyAttendance::query()->where('date', $date)->whereIn('student_id', $ids)->get()->keyBy('student_id');
        $marks = $session === null ? collect() : $session->attendances()->get()->keyBy('student_id');

        return array_map(function ($student) use ($daily, $marks): array {
            /** @var AttendanceStatus|null $day */
            $day = $daily->get($student->id)?->status;
            $mark = $marks->get($student->id);

            $status = $mark->status
                ?? ($day !== null && ! $day->countsAsPresent() ? $day : null);

            return [
                'id' => $student->id,
                'name' => $student->name,
                'nis' => $student->nis,
                'status' => $status?->value,
                'scanned' => $mark?->scanned_at !== null,
                'daily' => $day?->value,
            ];
        }, $members);
    }
}
