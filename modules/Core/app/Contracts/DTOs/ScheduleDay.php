<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * One weekday of a teacher's timetable; `day` is 1 (Senin) to 7 (Minggu).
 * Only days with at least one lesson are returned.
 */
final readonly class ScheduleDay
{
    /**
     * @param  list<ScheduleLesson>  $lessons
     */
    public function __construct(
        public int $day,
        public string $dayName,
        public array $lessons,
    ) {}
}
