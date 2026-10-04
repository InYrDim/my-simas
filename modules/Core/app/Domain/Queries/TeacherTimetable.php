<?php

namespace Modules\Core\App\Domain\Queries;

use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;

/**
 * The weekly timetable of the teacher behind a signed-in account: the
 * lessons of the active year in the classes and subjects they are
 * assigned, by weekday. Days without a lesson are left out.
 */
final class TeacherTimetable
{
    /**
     * @return list<array{day: string, dayNumber: int, lessons: list<array{order: int, start: string, end: string, class: string, subject: string}>}>
     */
    public function forUser(?int $userId): array
    {
        $teacher = $userId === null ? null : Teacher::query()->where('user_id', $userId)->first();

        if ($teacher === null) {
            return [];
        }

        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->whereHas('classGroup.academicYear', fn ($query) => $query->where('status', AcademicYearStatus::Active->value))
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $mine = $assignments->mapWithKeys(fn (TeachingAssignment $assignment): array => ["{$assignment->class_id}:{$assignment->subject_id}" => true]);

        $entries = TimetableEntry::query()
            ->with(['classGroup', 'subject'])
            ->whereIn('class_id', $assignments->pluck('class_id'))
            ->get()
            ->filter(fn (TimetableEntry $entry): bool => $mine->has("{$entry->class_id}:{$entry->subject_id}"))
            ->groupBy('period_slot_id');

        $slots = PeriodSlot::query()->where('type', PeriodSlot::LESSON)->orderBy('day')->orderBy('start_time')->get()->groupBy('day');

        $days = [];

        foreach (PeriodSlot::DAYS as $number => $name) {
            $order = 0;
            $lessons = [];

            foreach ($slots->get($number, collect()) as $slot) {
                $order++;

                foreach ($entries->get($slot->id, collect()) as $entry) {
                    $lessons[] = [
                        'order' => $order,
                        'start' => substr($slot->start_time, 0, 5),
                        'end' => substr($slot->end_time, 0, 5),
                        'class' => $entry->classGroup->name,
                        'subject' => $entry->subject->name,
                    ];
                }
            }

            if ($lessons !== []) {
                $days[] = ['day' => $name, 'dayNumber' => $number, 'lessons' => $lessons];
            }
        }

        return $days;
    }
}
