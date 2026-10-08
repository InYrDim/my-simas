<?php

namespace Modules\Platform\App\Infrastructure\Billing\Daily;

use Carbon\CarbonInterface;

/**
 * The outcome of one billing:daily run, step by step.
 */
final readonly class BillingDailyRun
{
    /**
     * @param  list<DailyStepResult>  $results
     */
    public function __construct(
        public CarbonInterface $day,
        public array $results,
        public bool $dryRun,
    ) {}

    public function hasFailures(): bool
    {
        foreach ($this->results as $result) {
            if ($result->failed()) {
                return true;
            }
        }

        return false;
    }

    public function isBlocked(): bool
    {
        foreach ($this->results as $result) {
            if ($result->blocked) {
                return true;
            }
        }

        return false;
    }
}
