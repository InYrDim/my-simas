<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\ScheduleDay;

/**
 * Read access to the weekly lesson timetable (jadwal pelajaran) of one
 * class, as a student of it sees it: each lesson names its subject and
 * the teacher the class teaching assignment gives it.
 */
interface ClassTimetable
{
    /**
     * The class's whole week, ordered by weekday and bell order; only
     * days with lessons are returned. Empty for an unknown class.
     *
     * @return list<ScheduleDay>
     */
    public function week(int $classId): array;
}
