<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * A school's subscription at a glance. `state` is `trial`,
 * `trial_expired`, `active`, `due`, `overdue`, `cancelled`, `exempt` (the
 * provider does not bill this school) or `none` (no subscription yet).
 * Dates are `Y-m-d`; `price` is the plan's price for the current cycle in
 * whole rupiah.
 */
final readonly class BillingOverview
{
    public const STATE_NONE = 'none';

    public const STATE_EXEMPT = 'exempt';

    /**
     * @param  string|null  $cycle  `monthly` or `yearly`
     * @param  list<array{key: string, label: string}>  $modules  modules active for the school
     * @param  list<UsageLine>  $usage
     */
    public function __construct(
        public string $state,
        public ?string $planKey,
        public ?string $planName,
        public ?string $cycle,
        public ?int $price,
        public ?string $trialEndsOn,
        public ?string $periodStart,
        public ?string $periodEnd,
        public ?string $accessEndsOn,
        public ?string $scheduledPlanKey,
        public ?string $scheduledPlanName,
        public array $modules,
        public array $usage,
        public bool $canSubscribe,
        public bool $canChangePlan,
    ) {}
}
