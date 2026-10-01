<?php

namespace Modules\Platform\App\Contracts;

/**
 * Tenant-scoped role materialisation with an EXPLICIT tenant id — safe
 * to call from central hosts, CLI commands, and queued listeners where
 * no ambient context exists (a missing context must never silently
 * create a GLOBAL role).
 *
 * Role names are machine names (stable keys in code, DB, and exports);
 * display labels live in the owning module's config. Idempotent by
 * design: calling ensure() repeatedly converges the role and its
 * permission set to the requested state.
 */
interface TenantRoles
{
    /**
     * Ensure the named role exists for the given tenant, holding
     * exactly the given permission set (permissions are created if
     * missing; empty set = role without permissions). Throws when the
     * tenant id is unknown — fail closed, never a global role.
     *
     * @param  array<int, string>  $permissions
     */
    public function ensure(string $tenantId, string $name, array $permissions = []): void;

    /**
     * All role names visible to the tenant: its own rows plus global
     * roles (Spatie teams semantics). Machine names, unsorted.
     *
     * @return array<int, string>
     */
    public function names(string $tenantId): array;

    /**
     * Each visible role of the tenant with its permission names (sorted),
     * keyed by role machine name. Same visibility as names().
     *
     * @return array<string, array<int, string>>
     */
    public function rolePermissions(string $tenantId): array;
}
