<?php

namespace Modules\Platform\App\Contracts;

use Closure;

/**
 * Registry of the usage figures shown against a school's plan limits.
 * Each module registers its own meters from its own service provider;
 * Platform never counts another module's data. A meter of a module that
 * is not active for the school is hidden.
 */
interface UsageMeters
{
    /**
     * Register one meter for the given module key. Called from the owning
     * module's service provider during boot. Idempotent per meter key.
     *
     * The counter runs inside the school's tenant context and returns the
     * current figure in the meter's unit. The plan limit is read from the
     * plan limit named `$limitKey` (defaults to the meter key).
     *
     * @param  Closure(): int  $counter
     */
    public function register(string $module, string $key, string $label, string $unit, Closure $counter, ?string $limitKey = null): void;
}
