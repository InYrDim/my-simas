<?php

namespace Modules\Attendance\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Attendance\App\Domain\Enums\LessonState;
use Modules\Attendance\App\Domain\Models\LessonCheck;
use Modules\Attendance\App\Domain\Queries\TeacherLessons;
use Modules\Attendance\App\Domain\Support\SchoolClock;

/**
 * Ticks or unticks one of today's lessons on Jadwal Hari Ini. A lesson
 * can only be ticked once its hour is over; un-ticking is always
 * allowed, so a mistaken tick (or the one saving the attendance left)
 * can be taken back.
 */
final class ToggleLessonCheck
{
    public function __construct(
        private readonly TeacherLessons $lessons,
        private readonly SchoolClock $clock,
    ) {}

    /**
     * @return bool the state after the change
     *
     * @throws ValidationException
     */
    public function handle(int $userId, int $slotId, bool $checked): bool
    {
        $today = $this->clock->today();
        $lesson = $this->lessons->find($userId, $today, $slotId);

        if ($lesson === null) {
            throw ValidationException::withMessages(['period_slot_id' => 'Jam ini bukan jadwal mengajar Anda hari ini.']);
        }

        if (! $checked) {
            LessonCheck::query()
                ->where('user_id', $userId)
                ->where('date', $today)
                ->where('period_slot_id', $slotId)
                ->delete();

            return false;
        }

        if ($lesson['state'] !== LessonState::Finished->value) {
            throw ValidationException::withMessages(['period_slot_id' => 'Tandai selesai setelah jam pelajaran berakhir.']);
        }

        LessonCheck::query()->updateOrCreate(
            ['user_id' => $userId, 'date' => $today, 'period_slot_id' => $slotId],
            ['class_id' => $lesson['classId'], 'checked_at' => $this->clock->stored($this->clock->now())],
        );

        return true;
    }
}
