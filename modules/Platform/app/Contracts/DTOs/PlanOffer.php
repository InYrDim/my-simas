<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * A public plan a school may choose. `direction` compares it with the
 * school's current plan at its current cycle (`current`, `upgrade`,
 * `downgrade`), and is null for a school without a subscription.
 * `modulesLost` and `overLimits` say what moving to this plan would cost:
 * the modules the school would lose and the figures that would sit above
 * the plan's limits (allowed, shown as over the limit).
 */
final readonly class PlanOffer
{
    public const CURRENT = 'current';

    public const UPGRADE = 'upgrade';

    public const DOWNGRADE = 'downgrade';

    /**
     * @param  array{students: int|null, staffAccounts: int|null, storageMb: int|null}  $limits
     * @param  list<array{key: string, label: string}>  $modules
     * @param  list<string>  $modulesLost  module labels
     * @param  list<array{label: string, unit: string, used: int, limit: int}>  $overLimits
     */
    public function __construct(
        public string $key,
        public string $name,
        public int $priceMonthly,
        public int $priceYearly,
        public array $limits,
        public array $modules,
        public ?string $direction,
        public array $modulesLost = [],
        public array $overLimits = [],
    ) {}
}
