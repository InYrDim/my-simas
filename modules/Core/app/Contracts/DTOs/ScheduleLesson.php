<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * One lesson of a teacher's weekly timetable: a class, a subject and a
 * bell slot. `order` is the slot's place among that day's lesson slots
 * ("jam ke-N"); times are `H:i`. `teacherName` is only filled on a
 * class timetable (`ClassTimetable`), where the teacher is not the reader.
 */
final readonly class ScheduleLesson
{
    public function __construct(
        public int $slotId,
        public int $order,
        public string $startsAt,
        public string $endsAt,
        public int $classId,
        public string $className,
        public int $subjectId,
        public string $subjectName,
        public ?string $teacherName = null,
    ) {}
}
