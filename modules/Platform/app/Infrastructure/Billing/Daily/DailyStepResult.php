<?php

namespace Modules\Platform\App\Infrastructure\Billing\Daily;

/**
 * What one billing:daily step did (or, in a dry run, would do).
 */
final readonly class DailyStepResult
{
    /**
     * @param  list<string>  $lines  one readable line per school or item touched
     * @param  list<string>  $failures  items that raised an error; the step went on without them
     * @param  bool  $blocked  the step held itself back on purpose (suspension cap) and needs the provider
     */
    public function __construct(
        public string $step,
        public int $count = 0,
        public array $lines = [],
        public array $failures = [],
        public bool $blocked = false,
    ) {}

    public function failed(): bool
    {
        return $this->failures !== [];
    }
}
