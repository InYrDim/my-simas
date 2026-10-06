<?php

namespace Modules\Attendance\App\Domain\Queries;

use Modules\Attendance\App\Domain\Enums\LessonState;
use Modules\Attendance\App\Domain\Models\LessonCheck;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\DTOs\ScheduleLesson;
use Modules\Core\App\Contracts\TeacherSchedule;

/**
 * The lessons a teacher has on one day, from their own timetable, each
 * with where it stands on the school's clock, whether its attendance was
 * saved and whether the teacher ticked it off.
 */
final class TeacherLessons
{
    public function __construct(
        private readonly TeacherSchedule $schedule,
        private readonly SchoolClock $clock,
    ) {}

    /**
     * @param  string  $date  Y-m-d
     * @return list<array{slotId: int, order: int, startsAt: string, endsAt: string, classId: int, className: string, subjectId: int, subjectName: string, state: string, recorded: bool, checked: bool}>
     */
    public function on(int $userId, string $date): array
    {
        $lessons = $this->schedule->onDay($userId, $this->clock->weekday($date));

        if ($lessons === []) {
            return [];
        }

        $classIds = array_values(array_unique(array_map(fn (ScheduleLesson $lesson): int => $lesson->classId, $lessons)));

        $recorded = LessonSession::query()
            ->where('date', $date)
            ->whereIn('class_id', $classIds)
            ->get()
            ->keyBy(fn (LessonSession $session): string => "{$session->class_id}:{$session->period_slot_id}");

        $checked = LessonCheck::query()
            ->where('user_id', $userId)
            ->where('date', $date)
            ->pluck('period_slot_id')
            ->all();

        return array_map(fn (ScheduleLesson $lesson): array => [
            'slotId' => $lesson->slotId,
            'order' => $lesson->order,
            'startsAt' => $lesson->startsAt,
            'endsAt' => $lesson->endsAt,
            'classId' => $lesson->classId,
            'className' => $lesson->className,
            'subjectId' => $lesson->subjectId,
            'subjectName' => $lesson->subjectName,
            'state' => $this->state($date, $lesson)->value,
            'recorded' => $recorded->has("{$lesson->classId}:{$lesson->slotId}"),
            'checked' => in_array($lesson->slotId, $checked, true),
        ], $lessons);
    }

    /**
     * One of the day's lessons, or null when the slot is not the
     * teacher's that day.
     *
     * @return array{slotId: int, order: int, startsAt: string, endsAt: string, classId: int, className: string, subjectId: int, subjectName: string, state: string, recorded: bool, checked: bool}|null
     */
    public function find(int $userId, string $date, int $slotId): ?array
    {
        foreach ($this->on($userId, $date) as $lesson) {
            if ($lesson['slotId'] === $slotId) {
                return $lesson;
            }
        }

        return null;
    }

    /**
     * Where the day as a whole stands: no lessons, all still to come, in
     * the middle of the teaching range, or over. The range runs from the
     * first lesson's start to the last lesson's end.
     *
     * @param  list<array{startsAt: string, endsAt: string}>  $lessons
     */
    public function rangeState(array $lessons): string
    {
        if ($lessons === []) {
            return 'none';
        }

        $now = $this->clock->now()->format('H:i');
        $first = min(array_column($lessons, 'startsAt'));
        $last = max(array_column($lessons, 'endsAt'));

        if ($now < $first) {
            return 'upcoming';
        }

        return $now < $last ? 'running' : 'finished';
    }

    private function state(string $date, ScheduleLesson $lesson): LessonState
    {
        $today = $this->clock->today();

        if ($date !== $today) {
            return $date < $today ? LessonState::Finished : LessonState::Upcoming;
        }

        $now = $this->clock->now()->format('H:i');

        if ($now < $lesson->startsAt) {
            return LessonState::Upcoming;
        }

        return $now < $lesson->endsAt ? LessonState::Running : LessonState::Finished;
    }
}
