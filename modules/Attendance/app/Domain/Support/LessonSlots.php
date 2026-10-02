<?php

namespace Modules\Attendance\App\Domain\Support;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Contracts\BellSchedule;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\DTOs\BellSlot;
use Modules\Core\App\Contracts\DTOs\ClassRecord;

/**
 * The rules every lesson record shares: the class belongs to the active
 * academic year, the day has come, and the slot is a lesson slot of that
 * day's weekday.
 */
final class LessonSlots
{
    public function __construct(
        private readonly ClassDirectory $classes,
        private readonly BellSchedule $schedule,
        private readonly SchoolClock $clock,
    ) {}

    /**
     * The lesson slots of a day, by start time.
     *
     * @param  string  $date  Y-m-d
     * @return list<BellSlot>
     */
    public function on(string $date): array
    {
        return array_values(array_filter(
            $this->schedule->slotsOn($this->clock->weekday($date)),
            fn (BellSlot $slot): bool => $slot->isLesson,
        ));
    }

    /**
     * @param  string  $date  Y-m-d
     * @return array{0: ClassRecord, 1: BellSlot}
     *
     * @throws ValidationException
     */
    public function resolve(int $classId, string $date, int $slotId): array
    {
        $class = collect($this->classes->ofActiveYear())->firstWhere('id', $classId);

        if ($class === null) {
            throw ValidationException::withMessages(['class_id' => 'Kelas tidak ada di tahun ajaran aktif.']);
        }

        if ($date > $this->clock->today()) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak boleh melewati hari ini.']);
        }

        $slot = $this->schedule->find($slotId);

        if ($slot === null || ! $slot->isLesson) {
            throw ValidationException::withMessages(['period_slot_id' => 'Jam ini bukan jam pelajaran.']);
        }

        if ($slot->day !== $this->clock->weekday($date)) {
            throw ValidationException::withMessages(['period_slot_id' => 'Jam ini bukan jam pelajaran pada hari tersebut.']);
        }

        return [$class, $slot];
    }
}
