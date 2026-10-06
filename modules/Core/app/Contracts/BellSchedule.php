<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\BellSlot;

/**
 * Read access to the current school's bell schedule for other modules.
 */
interface BellSchedule
{
    /**
     * The slots of a weekday (1 = Senin ... 7 = Minggu), by start time.
     *
     * @return list<BellSlot>
     */
    public function slotsOn(int $day): array;

    public function find(int $slotId): ?BellSlot;
}
