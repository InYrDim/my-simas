<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;

/**
 * Puts a subject in a lesson slot of a class timetable, or empties the
 * slot (no subject). The subject must have a teacher in that class, and
 * that teacher may not already teach another class in the same slot.
 */
final class SaveTimetableEntry
{
    /**
     * @throws ValidationException
     */
    public function handle(int $periodSlotId, ClassGroup $class, ?int $subjectId): void
    {
        $slot = PeriodSlot::query()->whereKey($periodSlotId)->where('type', PeriodSlot::LESSON)->first();

        if ($slot === null) {
            throw ValidationException::withMessages(['period_slot_id' => 'Jam pelajaran tidak ditemukan.']);
        }

        $inActiveYear = $class->academicYear()->where('status', AcademicYearStatus::Active->value)->exists();

        if (! $inActiveYear) {
            throw ValidationException::withMessages(['class_id' => 'Hanya kelas pada tahun ajaran aktif yang bisa dijadwalkan.']);
        }

        if ($subjectId === null) {
            TimetableEntry::query()->where('period_slot_id', $slot->id)->where('class_id', $class->id)->delete();

            return;
        }

        $assignment = TeachingAssignment::query()
            ->where('class_id', $class->id)
            ->where('subject_id', $subjectId)
            ->first();

        if ($assignment === null) {
            throw ValidationException::withMessages(['subject_id' => 'Mata pelajaran ini belum punya pengampu di kelas ini. Atur dulu di Pengampu Mapel.']);
        }

        $this->refuseDoubleBooking($slot, $class, $assignment);

        TimetableEntry::query()->updateOrCreate(
            ['period_slot_id' => $slot->id, 'class_id' => $class->id],
            ['subject_id' => $subjectId],
        );
    }

    /**
     * @throws ValidationException
     */
    private function refuseDoubleBooking(PeriodSlot $slot, ClassGroup $class, TeachingAssignment $assignment): void
    {
        $others = TimetableEntry::query()
            ->with('classGroup')
            ->where('period_slot_id', $slot->id)
            ->where('class_id', '!=', $class->id)
            ->get();

        foreach ($others as $entry) {
            $busy = TeachingAssignment::query()
                ->where('class_id', $entry->class_id)
                ->where('subject_id', $entry->subject_id)
                ->where('teacher_id', $assignment->teacher_id)
                ->exists();

            if ($busy) {
                throw ValidationException::withMessages([
                    'subject_id' => "Guru ini sudah mengajar di kelas {$entry->classGroup->name} pada jam yang sama.",
                ]);
            }
        }
    }
}
