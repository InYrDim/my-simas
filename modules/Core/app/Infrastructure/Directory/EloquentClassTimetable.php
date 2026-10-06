<?php

namespace Modules\Core\App\Infrastructure\Directory;

use Modules\Core\App\Contracts\ClassTimetable;
use Modules\Core\App\Contracts\DTOs\ScheduleDay;
use Modules\Core\App\Contracts\DTOs\ScheduleLesson;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;

final class EloquentClassTimetable implements ClassTimetable
{
    public function week(int $classId): array
    {
        $entries = TimetableEntry::query()
            ->with(['classGroup', 'subject'])
            ->where('class_id', $classId)
            ->get()
            ->keyBy('period_slot_id');

        if ($entries->isEmpty()) {
            return [];
        }

        $teachers = TeachingAssignment::query()
            ->with('teacher')
            ->where('class_id', $classId)
            ->get()
            ->mapWithKeys(fn (TeachingAssignment $assignment): array => [$assignment->subject_id => $assignment->teacher?->name]);

        $slotsByDay = PeriodSlot::query()
            ->where('type', PeriodSlot::LESSON)
            ->orderBy('day')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day');

        $days = [];

        foreach (PeriodSlot::DAYS as $number => $name) {
            $order = 0;
            $lessons = [];

            foreach ($slotsByDay->get($number, []) as $slot) {
                $order++;
                $entry = $entries->get($slot->id);

                if ($entry === null) {
                    continue;
                }

                $lessons[] = new ScheduleLesson(
                    slotId: $slot->id,
                    order: $order,
                    startsAt: substr($slot->start_time, 0, 5),
                    endsAt: substr($slot->end_time, 0, 5),
                    classId: $entry->class_id,
                    className: $entry->classGroup->name,
                    subjectId: $entry->subject_id,
                    subjectName: $entry->subject->name,
                    teacherName: $teachers->get($entry->subject_id),
                );
            }

            if ($lessons !== []) {
                $days[] = new ScheduleDay(day: $number, dayName: $name, lessons: $lessons);
            }
        }

        return $days;
    }
}
