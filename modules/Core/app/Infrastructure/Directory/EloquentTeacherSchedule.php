<?php

namespace Modules\Core\App\Infrastructure\Directory;

use Modules\Core\App\Contracts\DTOs\ScheduleDay;
use Modules\Core\App\Contracts\DTOs\ScheduleLesson;
use Modules\Core\App\Contracts\TeacherSchedule;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;

final class EloquentTeacherSchedule implements TeacherSchedule
{
    public function week(int $userId): array
    {
        $source = $this->source($userId);

        if ($source === null) {
            return [];
        }

        [$slots, $entries] = $source;

        $days = [];

        foreach (PeriodSlot::DAYS as $number => $name) {
            $lessons = $this->lessons($slots[$number] ?? [], $entries);

            if ($lessons !== []) {
                $days[] = new ScheduleDay(day: $number, dayName: $name, lessons: $lessons);
            }
        }

        return $days;
    }

    public function onDay(int $userId, int $day): array
    {
        $source = $this->source($userId);

        return $source === null ? [] : $this->lessons($source[0][$day] ?? [], $source[1]);
    }

    /**
     * The teacher behind the account, the lesson slots by weekday and the
     * timetable entries of the teacher's own class+subject pairs, by
     * slot. Null when the account is not a teacher's or the teacher has
     * no teaching assignment in the active academic year.
     *
     * @return array{0: array<int|string, list<PeriodSlot>>, 1: array<int|string, list<TimetableEntry>>}|null
     */
    private function source(int $userId): ?array
    {
        $teacher = Teacher::query()->where('user_id', $userId)->first();

        if ($teacher === null) {
            return null;
        }

        $assignments = TeachingAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->whereHas('classGroup.academicYear', fn ($query) => $query->where('status', AcademicYearStatus::Active->value))
            ->get();

        if ($assignments->isEmpty()) {
            return null;
        }

        $mine = $assignments->mapWithKeys(fn (TeachingAssignment $assignment): array => [
            "{$assignment->class_id}:{$assignment->subject_id}" => true,
        ]);

        /** @var array<int|string, list<TimetableEntry>> $entries */
        $entries = [];

        $bySlot = TimetableEntry::query()
            ->with(['classGroup', 'subject'])
            ->whereIn('class_id', $assignments->pluck('class_id'))
            ->get()
            ->filter(fn (TimetableEntry $entry): bool => $mine->has("{$entry->class_id}:{$entry->subject_id}"))
            ->groupBy('period_slot_id');

        foreach ($bySlot as $slotId => $rows) {
            $entries[$slotId] = array_values($rows->all());
        }

        /** @var array<int|string, list<PeriodSlot>> $slots */
        $slots = [];

        $byDay = PeriodSlot::query()
            ->where('type', PeriodSlot::LESSON)
            ->orderBy('day')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day');

        foreach ($byDay as $day => $rows) {
            $slots[$day] = array_values($rows->all());
        }

        return [$slots, $entries];
    }

    /**
     * One day's lessons in bell order: only slots that carry an entry,
     * numbered by their place among all lesson slots of the day.
     *
     * @param  list<PeriodSlot>  $slots
     * @param  array<int|string, list<TimetableEntry>>  $entries
     * @return list<ScheduleLesson>
     */
    private function lessons(array $slots, array $entries): array
    {
        $order = 0;
        $lessons = [];

        foreach ($slots as $slot) {
            $order++;

            foreach ($entries[$slot->id] ?? [] as $entry) {
                $lessons[] = new ScheduleLesson(
                    slotId: $slot->id,
                    order: $order,
                    startsAt: substr($slot->start_time, 0, 5),
                    endsAt: substr($slot->end_time, 0, 5),
                    classId: $entry->class_id,
                    className: $entry->classGroup->name,
                    subjectId: $entry->subject_id,
                    subjectName: $entry->subject->name,
                );
            }
        }

        return $lessons;
    }
}
