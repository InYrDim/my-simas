<?php

namespace Modules\Platform\App\Infrastructure\Permissions;

use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantRoles;
use Modules\Platform\App\Infrastructure\Tenancy\TenantHydrator;
use Spatie\Permission\Models\Role;

/**
 * Default TenantRoles: materialise tenant-owned role rows under an
 * explicit tenant id. Implementation detail of the public contract —
 * consumers bind/type-hint the interface, never this class.
 *
 * Failure modes are deliberate:
 * - Unknown tenant id → InvalidArgumentException BEFORE any context is
 *   set (fail closed: a missing/unknown tenant must never fall through
 *   to a GLOBAL role row).
 * - Permission sync runs first (idempotent) so attaching never
 *   references missing global permission rows.
 *
 * Consumed by Identity's SeedDefaultRoles listener, which runs from the
 * Tenant model's `created` hook — i.e. inside factory/CLI/central
 * contexts with no ambient tenant.
 */
final class DefaultTenantRoles implements TenantRoles
{
    public function __construct(
        private readonly TenantRoleResolver $roles,
        private readonly PermissionSync $sync,
    ) {}

    public function ensure(string $tenantId, string $name, array $permissions = []): void
    {
        // Fail closed BEFORE touching the context: resolveOrCreate()
        // relies on ambient context and a roles.tenant_id FK violation
        // for an unknown tenant id would surface as a raw query error.
        if (TenantHydrator::find($tenantId) === null) {
            throw new \InvalidArgumentException(
                "Cannot ensure role [{$name}]: tenant [{$tenantId}] does not exist.",
            );
        }

        // Permission rows are GLOBAL; sync makes sure every name being
        // attached exists (idempotent, context-free, restores ambient).
        if ($permissions !== []) {
            $this->sync->ensurePermissions($permissions);
        }

        // Run inside the tenant's context so the resolver targets THIS
        // tenant's role row (and Spatie's team pointer agrees).
        app(TenantContext::class)->run(
            $tenantId,
            function () use ($name, $permissions): void {
                $role = $this->roles->resolveOrCreate($name, 'web');

                if ($permissions !== []) {
                    $role->syncPermissions($permissions);
                }
            },
        );
    }

    public function names(string $tenantId): array
    {
        if (TenantHydrator::find($tenantId) === null) {
            throw new \InvalidArgumentException(
                "Cannot list roles: tenant [{$tenantId}] does not exist.",
            );
        }

        /** @var array<int, string> */
        return app(TenantContext::class)->run(
            $tenantId,
            fn (): array => Role::query()
                ->where('tenant_id', $tenantId)
                ->orWhereNull('tenant_id')
                ->orderBy('name')
                ->pluck('name')
                ->all(),
        );
    }
}
