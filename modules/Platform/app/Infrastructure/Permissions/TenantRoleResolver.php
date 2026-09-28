<?php

namespace Modules\Platform\App\Infrastructure\Permissions;

use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Spatie\Permission\Models\Role;

/**
 * Resolve-or-create tenant-scoped role rows. Global roles (roles with
 * a NULL tenant_id — Spatie teams semantics) are visible in every
 * tenant; per-tenant rows win via the (tenant_id, name, guard_name)
 * unique key.
 *
 * Runs inside TenantPermissionBridge's seam: Spatie is never imported
 * outside Platform's permission internals.
 */
final class TenantRoleResolver
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * Find the role for the current tenant, creating the tenant's own
     * row when missing. Throws without tenant context (role rows are
     * tenant-owned; a missing context would silently create a global
     * role).
     */
    public function resolveOrCreate(string $name, string $guard): Role
    {
        $tenantId = $this->context->id()
            ?? throw new TenantNotSetException(
                "Resolving role [{$name}] requires tenant context. Wrap in TenantContext::run().",
            );

        $role = Role::query()
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($role !== null) {
            return $role;
        }

        // Create-if-missing (unique key guards against races).
        return Role::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => $guard, 'tenant_id' => $tenantId],
        );
    }

    /**
     * Find the role for the current tenant WITHOUT creating it —
     * removal must never materialise rows. Null when the tenant has no
     * such role (nothing to remove).
     */
    public function resolveOrNull(string $name, string $guard): ?Role
    {
        $tenantId = $this->context->id()
            ?? throw new TenantNotSetException(
                "Resolving role [{$name}] requires tenant context. Wrap in TenantContext::run().",
            );

        return Role::query()
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->where('tenant_id', $tenantId)
            ->first();
    }
}
