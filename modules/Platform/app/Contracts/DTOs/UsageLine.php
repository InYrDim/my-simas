<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * One measured usage figure of a school against its plan ceiling.
 * `limit` is null when the plan sets none (unlimited); `state` is `ok`,
 * `near` (at or above 90% of the limit) or `over` (above the limit).
 */
final readonly class UsageLine
{
    public const STATE_OK = 'ok';

    public const STATE_NEAR = 'near';

    public const STATE_OVER = 'over';

    public function __construct(
        public string $key,
        public string $label,
        public string $unit,
        public int $used,
        public ?int $limit,
        public string $state,
    ) {}

    public function isOver(): bool
    {
        return $this->state === self::STATE_OVER;
    }
}
