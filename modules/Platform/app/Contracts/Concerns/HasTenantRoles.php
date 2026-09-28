<?php

namespace Modules\Platform\App\Contracts\Concerns;

use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Infrastructure\Permissions\TenantPermissionBridge;
use Modules\Platform\App\Infrastructure\Permissions\TenantRoleResolver;
use Spatie\Permission\Traits\HasRoles;

/**
 * Tenant-safe wrapper around Spatie's HasRoles for user-like models.
 * Consumers NEVER import Spatie classes directly — this trait pulls in
 * Spatie's HasRoles itself (Platform is the only module allowed to
 * touch Spatie, per the layer rules), so `use HasTenantRoles` is the
 * complete surface for Identity (Stage 9).
 *
 * Permission checks resolve against the CURRENT tenant (Spatie "team"
 * = tenant, kept in sync by Platform's tenant bridge). Role names may
 * repeat across tenants; the (tenant_id, name, guard_name) unique key
 * keeps them apart.
 */
trait HasTenantRoles
{
    use HasRoles;

    /**
     * Assign the named role for the current tenant. Creates the tenant's
     * role row on first use (create-if-missing, idempotent).
     */
    public function assignTenantRole(string $name): void
    {
        $role = app(TenantRoleResolver::class)->resolveOrCreate(
            $name,
            $this->guard_name ?? 'web',
        );

        $this->assignRole($role);
    }

    /**
     * Remove the named role for the current tenant. No-op when the
     * tenant has no such role (removal never creates rows).
     */
    public function removeTenantRole(string $name): void
    {
        $role = app(TenantRoleResolver::class)->resolveOrNull(
            $name,
            $this->guard_name ?? 'web',
        );

        if ($role !== null) {
            $this->removeRole($role);
        }
    }

    /**
     * Whether the model has the named role in the current tenant.
     */
    public function hasTenantRole(string $name): bool
    {
        return $this->hasRole($name);
    }

    /**
     * All role names visible in the current tenant (its own rows plus
     * global roles — Spatie teams semantics).
     *
     * @return array<int, string>
     */
    public function tenantRoleNames(): array
    {
        return $this->getRoleNames()->all();
    }

    /**
     * Flush Spatie's permission cache after direct pivot writes.
     */
    public static function flushTenantPermissionCache(): void
    {
        app(TenantPermissionBridge::class)->forgetPermissionCache();
    }

    /**
     * Ensure Spatie's team pointer matches the ambient tenant context
     * before permission work (safety net for queued/CLI callers).
     */
    protected function syncTenantTeam(): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId !== null) {
            app(TenantPermissionBridge::class)->setTeam($tenantId);
        }
    }
}
