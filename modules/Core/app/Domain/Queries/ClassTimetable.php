<?php

namespace Modules\Core\App\Domain\Queries;

use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;

/**
 * One class weekly timetable: for each weekday its lesson slots, each
 * with the subject put there and the teacher the class assignment names.
 */
final class ClassTimetable
{
    /**
     * @return list<array{day: string, dayNumber: int, slots: list<array{id: int, order: int, start: string, end: string, subjectId: int|null, subject: string|null, teacher: string|null}>}>
     */
    public function forClass(int $classId): array
    {
        $entries = TimetableEntry::query()->with('subject')->where('class_id', $classId)->get()->keyBy('period_slot_id');
        $teachers = TeachingAssignment::query()->with('teacher')->where('class_id', $classId)->get()->keyBy('subject_id');

        $slots = PeriodSlot::query()->where('type', PeriodSlot::LESSON)->orderBy('day')->orderBy('start_time')->get()->groupBy('day');

        $days = [];

        foreach (PeriodSlot::DAYS as $number => $name) {
            $order = 0;
            $rows = [];

            foreach ($slots->get($number, collect()) as $slot) {
                $entry = $entries->get($slot->id);

                $rows[] = [
                    'id' => $slot->id,
                    'order' => ++$order,
                    'start' => substr($slot->start_time, 0, 5),
                    'end' => substr($slot->end_time, 0, 5),
                    'subjectId' => $entry?->subject_id,
                    'subject' => $entry?->subject->name,
                    'teacher' => $entry === null ? null : $teachers->get($entry->subject_id)?->teacher->name,
                ];
            }

            $days[] = ['day' => $name, 'dayNumber' => $number, 'slots' => $rows];
        }

        return $days;
    }
}
