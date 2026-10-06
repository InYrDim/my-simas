<?php

namespace Modules\Attendance\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\LessonState;
use Modules\Attendance\App\Domain\Models\LessonCheck;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Queries\TeacherLessons;
use Modules\Attendance\App\Domain\Support\SchoolClock;

/**
 * A teacher records the attendance of one of their own lessons: the
 * class, the subject and the hour come from their timetable, never from
 * the request. With `$whileRunning` (Absensi Kelas) it only works while
 * the lesson is in its own hour, today; without it (Riwayat Absensi) any
 * day up to today is allowed.
 *
 * Saving also ticks the lesson off the teacher's Jadwal Hari Ini todo.
 */
final class SaveOwnLessonAttendance
{
    public function __construct(
        private readonly TeacherLessons $lessons,
        private readonly SchoolClock $clock,
        private readonly SaveLessonAttendance $save,
    ) {}

    /**
     * @param  array<int, AttendanceStatus>  $marks  keyed by student id
     *
     * @throws ValidationException
     */
    public function handle(int $userId, string $date, int $slotId, array $marks, bool $whileRunning): LessonSession
    {
        $lesson = $this->lessons->find($userId, $date, $slotId);

        if ($lesson === null) {
            throw ValidationException::withMessages(['period_slot_id' => 'Jam ini bukan jadwal mengajar Anda.']);
        }

        if ($whileRunning && ($date !== $this->clock->today() || $lesson['state'] !== LessonState::Running->value)) {
            throw ValidationException::withMessages([
                'period_slot_id' => 'Absensi hanya dapat diisi saat jam pelajaran berlangsung. Ubah data lewat tab Koreksi.',
            ]);
        }

        $session = $this->save->handle($lesson['classId'], $date, $slotId, $lesson['subjectId'], $marks, $userId);

        LessonCheck::query()->updateOrCreate(
            ['user_id' => $userId, 'date' => $date, 'period_slot_id' => $slotId],
            ['class_id' => $lesson['classId'], 'checked_at' => $this->clock->stored($this->clock->now())],
        );

        return $session;
    }
}
