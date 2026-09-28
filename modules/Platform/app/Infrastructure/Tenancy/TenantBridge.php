<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Infrastructure\Permissions\TenantPermissionBridge;

/**
 * Internal seam between the tenant context and layers that must react to
 * context changes. Stage 6: the Spatie permission bridge is wired —
 * every context change points Spatie's "team" at the current tenant.
 */
final class TenantBridge
{
    public function __construct(
        private readonly TenantPermissionBridge $permissions,
    ) {}

    public function onTenantSet(string $tenantId): void
    {
        $this->permissions->setTeam($tenantId);
    }

    public function onTenantAdopted(TenantData $tenant): void
    {
        $this->permissions->adoptTenant($tenant);
    }

    public function onTenantForget(): void
    {
        $this->permissions->clearTeam();
    }

    public function onTenantRestored(?TenantData $tenant, string $tenantId): void
    {
        // Restore by id: the DTO may legitimately be absent (e.g. a
        // restore inside runWithoutTenant) but the team pointer must
        // still land back on the caller's tenant.
        $this->permissions->setTeam($tenantId);
    }

    /**
     * Load the tenant DTO for an id (plain column lookup; the tenants
     * table itself has no tenant_id).
     */
    public function hydrateTenant(string $tenantId): ?TenantData
    {
        $tenant = TenantHydrator::find($tenantId);

        return $tenant === null ? null : TenantData::fromTenant($tenant);
    }
}
