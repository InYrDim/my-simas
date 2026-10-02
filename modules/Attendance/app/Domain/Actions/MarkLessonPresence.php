<?php

namespace Modules\Attendance\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Exceptions\AttendanceException;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Support\LessonSlots;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\StudentDirectory;

final class MarkLessonPresence
{
    public function __construct(
        private readonly LessonSlots $slots,
        private readonly StudentDirectory $students,
        private readonly SchoolClock $clock,
    ) {}

    /**
     * One student present in a lesson of today, by scan or by hand. The
     * student must sit in that class; the session is opened when nobody
     * has yet. Marking a student who is already present is refused, so a
     * second scan is noticed.
     *
     * @throws AttendanceException
     * @throws ValidationException
     */
    public function handle(int $classId, int $slotId, int $studentId, RecordMethod $method, ?int $recordedBy): LessonAttendance
    {
        $today = $this->clock->today();
        [$class, $slot] = $this->slots->resolve($classId, $today, $slotId);

        $student = $this->students->find($studentId);

        if ($student === null || ! $student->active) {
            throw new AttendanceException('Siswa tidak ditemukan atau tidak aktif.');
        }

        if ($student->classId !== $classId) {
            throw new AttendanceException("{$student->name} bukan siswa kelas {$class->name}.");
        }

        return DB::transaction(function () use ($classId, $today, $slot, $student, $method, $recordedBy): LessonAttendance {
            $session = LessonSession::query()->firstOrCreate(
                ['class_id' => $classId, 'date' => $today, 'period_slot_id' => $slot->id],
                ['start_time' => "{$slot->startsAt}:00", 'end_time' => "{$slot->endsAt}:00", 'recorded_by' => $recordedBy],
            );

            $row = $session->attendances()->where('student_id', $student->id)->first();

            if ($row?->status === AttendanceStatus::Present) {
                throw new AttendanceException("{$student->name} sudah tercatat hadir di jam ini.");
            }

            $row ??= new LessonAttendance(['lesson_session_id' => $session->id, 'student_id' => $student->id]);
            $row->fill([
                'status' => AttendanceStatus::Present,
                'method' => $method,
                'scanned_at' => $this->clock->stored($this->clock->now()),
            ])->save();

            return $row;
        });
    }
}
