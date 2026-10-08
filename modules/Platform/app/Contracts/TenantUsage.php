<?php

namespace Modules\Platform\App\Contracts;

use Modules\Platform\App\Contracts\DTOs\UsageLine;

/**
 * What a school uses against its plan limits. Display only: nothing here
 * blocks an action.
 */
interface TenantUsage
{
    /**
     * Usage of one school, computed inside its tenant context. Meters of
     * modules that are not active for it are left out. A school without a
     * subscription, or a plan without the limit, has no limit.
     *
     * @return list<UsageLine>
     */
    public function forTenant(string $tenantId): array;

    /**
     * Whether the current school is above the limit of one meter. False
     * for an unknown meter or without a limit.
     */
    public function isOverLimit(string $key): bool;

    /**
     * How much of a meter the current school may still use; null when
     * there is no limit or no such meter, never below zero.
     */
    public function remaining(string $key): ?int;
}
