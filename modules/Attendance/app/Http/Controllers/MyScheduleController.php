<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Queries\TeacherLessons;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Core\App\Contracts\DTOs\ScheduleDay;
use Modules\Core\App\Contracts\DTOs\ScheduleLesson;
use Modules\Core\App\Contracts\TeacherSchedule;

/**
 * "Saya" › Jadwal Saya: the signed-in teacher's lessons in one place —
 * today as a todo list (with the banner that follows the teaching range)
 * and the whole week by weekday.
 */
final class MyScheduleController
{
    use KnowsSignedInUser;

    public function __invoke(TeacherLessons $lessons, SchoolClock $clock, TeacherSchedule $schedule): Response
    {
        $userId = $this->signedInUserId();
        $today = $clock->today();
        $list = $userId === null ? [] : $lessons->on($userId, $today);

        return Inertia::render('Attendance/MySchedule', [
            'date' => ['iso' => $today, 'label' => $clock->dateLabel($today)],
            'banner' => $lessons->rangeState($list),
            'lessons' => $list,
            'week' => $userId === null ? [] : $this->week($schedule->week($userId)),
        ]);
    }

    /**
     * @param  list<ScheduleDay>  $week
     * @return list<array{day: string, dayNumber: int, lessons: list<array{order: int, startsAt: string, endsAt: string, className: string, subjectName: string}>}>
     */
    private function week(array $week): array
    {
        return array_map(fn (ScheduleDay $day): array => [
            'day' => $day->dayName,
            'dayNumber' => $day->day,
            'lessons' => array_map(fn (ScheduleLesson $lesson): array => [
                'order' => $lesson->order,
                'startsAt' => $lesson->startsAt,
                'endsAt' => $lesson->endsAt,
                'className' => $lesson->className,
                'subjectName' => $lesson->subjectName,
            ], $day->lessons),
        ], $week);
    }
}
