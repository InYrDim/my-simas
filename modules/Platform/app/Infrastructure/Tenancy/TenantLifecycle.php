<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;

/**
 * Write path for a tenant's status and profile. Shared by the artisan
 * commands and the provider console so both flush the hydrator cache the
 * same way (a stale cached DTO would keep serving the old status).
 */
final class TenantLifecycle
{
    public function suspend(Tenant $tenant): Tenant
    {
        return $this->setStatus($tenant, TenantStatus::Suspended);
    }

    public function activate(Tenant $tenant): Tenant
    {
        return $this->setStatus($tenant, TenantStatus::Active);
    }

    public function setStatus(Tenant $tenant, TenantStatus $status): Tenant
    {
        if ($tenant->status === $status) {
            return $tenant;
        }

        $tenant->status = $status;
        $tenant->save();

        TenantHydrator::flush($tenant->id);

        return $tenant;
    }

    /**
     * @param  array{name: string, timezone: string, domain: string|null}  $attributes
     */
    public function updateProfile(Tenant $tenant, array $attributes): Tenant
    {
        $tenant->fill($attributes)->save();

        TenantHydrator::flush($tenant->id);

        return $tenant;
    }
}
