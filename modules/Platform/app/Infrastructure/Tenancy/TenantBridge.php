<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\TenantData;

/**
 * Internal seam between the tenant context and layers that must react to
 * context changes. Stage 2: hydrate tenants, keep DTO/id consistent.
 * Stage 6: Spatie setPermissionsTeamId + permission-cache resets hook in
 * here without touching the public contract.
 */
final class TenantBridge
{
    public function onTenantSet(string $tenantId): void
    {
        // Stage 6: setPermissionsTeamId($tenantId) + forget permission cache.
    }

    public function onTenantAdopted(TenantData $tenant): void
    {
        // Stage 6: setPermissionsTeamId($tenant->id).
    }

    public function onTenantForget(): void
    {
        // Stage 6: setPermissionsTeamId(null) + forget permission cache.
    }

    public function onTenantRestored(?TenantData $tenant, string $tenantId): void
    {
        // Stage 6: setPermissionsTeamId($tenantId).
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
