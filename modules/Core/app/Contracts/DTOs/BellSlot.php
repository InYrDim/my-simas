<?php

namespace Modules\Core\App\Contracts\DTOs;

/**
 * One slot of the school's bell schedule. Times are `H:i`; `day` is 1
 * (Senin) to 6 (Sabtu).
 */
final readonly class BellSlot
{
    public function __construct(
        public int $id,
        public int $day,
        public string $startsAt,
        public string $endsAt,
        public string $type,
        public bool $isLesson,
    ) {}
}
