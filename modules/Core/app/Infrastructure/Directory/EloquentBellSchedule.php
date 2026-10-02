<?php

namespace Modules\Core\App\Infrastructure\Directory;

use Modules\Core\App\Contracts\BellSchedule;
use Modules\Core\App\Contracts\DTOs\BellSlot;
use Modules\Core\App\Domain\Models\PeriodSlot;

final class EloquentBellSchedule implements BellSchedule
{
    public function slotsOn(int $day): array
    {
        return array_values(
            PeriodSlot::query()->where('day', $day)->orderBy('start_time')->get()->map($this->slot(...))->all(),
        );
    }

    public function find(int $slotId): ?BellSlot
    {
        $slot = PeriodSlot::query()->find($slotId);

        return $slot === null ? null : $this->slot($slot);
    }

    private function slot(PeriodSlot $slot): BellSlot
    {
        return new BellSlot(
            id: $slot->id,
            day: $slot->day,
            startsAt: substr($slot->start_time, 0, 5),
            endsAt: substr($slot->end_time, 0, 5),
            type: $slot->type,
            isLesson: $slot->type === PeriodSlot::LESSON,
        );
    }
}
