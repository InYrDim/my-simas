<?php

namespace Modules\Platform\App\Infrastructure\Permissions;

use Modules\Platform\App\Contracts\TenantData;
use Spatie\Permission\PermissionRegistrar;

/**
 * The ONLY place in the codebase allowed to call Spatie's team API
 * directly (setPermissionsTeamId + permission-cache resets). Other
 * modules — and Platform's own tenancy code — go through this seam so
 * the Spatie dependency stays contained behind Platform internals.
 */
final class TenantPermissionBridge
{
    public function __construct(
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * Point Spatie's "team" at the given tenant: role/permission
     * lookups and assignments now resolve against this tenant.
     */
    public function setTeam(string $tenantId): void
    {
        $this->registrar->setPermissionsTeamId($tenantId);
    }

    /**
     * Clear Spatie's team pointer (central context, no tenant).
     */
    public function clearTeam(): void
    {
        $this->registrar->setPermissionsTeamId(null);
    }

    /**
     * Adopt a fully-resolved tenant (middleware path).
     */
    public function adoptTenant(TenantData $tenant): void
    {
        $this->setTeam($tenant->id);
    }

    /**
     * Reset Spatie's permission cache. Needed after assignment changes
     * and after team switches (Octane/long-running workers).
     */
    public function forgetPermissionCache(): void
    {
        $this->registrar->forgetCachedPermissions();
    }
}
