<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\ScheduleDay;
use Modules\Core\App\Contracts\DTOs\ScheduleLesson;

/**
 * Read access to a teacher's lesson timetable (jadwal pelajaran): the
 * lessons of the classes and subjects the class teaching assignment
 * names for the teacher behind a login account. The teacher is never in
 * two classes in the same slot, so a slot identifies the lesson.
 */
interface TeacherSchedule
{
    /**
     * The teacher's whole week, ordered by weekday and bell order; only
     * days with lessons are returned. Empty when the account is not a
     * teacher's or has no lesson in the active academic year.
     *
     * @return list<ScheduleDay>
     */
    public function week(int $userId): array;

    /**
     * The teacher's lessons on one weekday (1 = Senin ... 7 = Minggu),
     * in bell order. Empty when the account is not a teacher's or has no
     * lesson that day.
     *
     * @return list<ScheduleLesson>
     */
    public function onDay(int $userId, int $day): array;
}
