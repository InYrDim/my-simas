<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Contracts\TenantDirectory;
use Modules\Platform\App\Domain\Models\Tenant;

/**
 * Default TenantDirectory: straight reads of the central tenants table.
 */
final class DefaultTenantDirectory implements TenantDirectory
{
    public function all(): array
    {
        return Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant): TenantData => TenantData::fromTenant($tenant))
            ->all();
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Tenant::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Tenant $tenant): array => [$tenant->id => TenantData::fromTenant($tenant)])
            ->all();
    }
}
