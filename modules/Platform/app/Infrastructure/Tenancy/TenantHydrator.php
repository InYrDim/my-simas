<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Support\Facades\Cache;
use Modules\Platform\App\Domain\Models\Tenant;

/**
 * Internal helper to load Tenant models by id with a small cache. The
 * tenants table is central data — it has no tenant_id and is never
 * scoped.
 */
final class TenantHydrator
{
    private const CACHE_TTL = 300;

    public static function find(string $tenantId): ?Tenant
    {
        return Cache::remember(
            'platform:tenant:id:'.$tenantId,
            self::CACHE_TTL,
            fn (): ?Tenant => Tenant::query()->find($tenantId),
        );
    }
}
