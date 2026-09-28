<?php

namespace Modules\Platform\App\Contracts;

/**
 * Registry of permission names that can be assigned per tenant. Each
 * module registers its own permission names from its own service
 * provider — Platform never hardcodes business permission names.
 *
 * Stage 7's `permissions:sync` command materialises registered
 * permissions idempotently (create-if-missing; never revokes).
 */
interface PermissionRegistry
{
    /**
     * Register permission names for the given module key. Called from
     * the owning module's service provider during boot. Idempotent.
     *
     * @param  array<int, string>  $permissions
     */
    public function register(string $module, array $permissions): void;

    /**
     * All registered permission names grouped by module key.
     *
     * @return array<string, array<int, string>>
     */
    public function all(): array;

    /**
     * Permission names registered by a specific module.
     *
     * @return array<int, string>
     */
    public function forModule(string $module): array;
}
