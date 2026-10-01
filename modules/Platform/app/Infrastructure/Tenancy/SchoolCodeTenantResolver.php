<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Support\Facades\Cache;
use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Domain\Models\Tenant;

/**
 * The one and only tenant resolution strategy: by school code.
 *
 * The school code is the tenant id (ULID) today; it becomes the NPSN
 * later — this class is the only place that mapping lives. Hosts play
 * no part: a tenant is chosen by the code typed on the login form (or
 * carried by an emailed link) and then remembered in the session.
 *
 * Codes that cannot possibly be a tenant id are rejected without a
 * query. Suspended tenants still resolve — the middleware decides 403.
 */
final class SchoolCodeTenantResolver implements TenantResolver
{
    /**
     * Cache TTL (seconds) for code → tenant id lookups.
     */
    private const CACHE_TTL = 300;

    public function resolve(string $code): TenantData
    {
        $code = strtolower(trim($code));

        if (! preg_match('/^[0-9a-hjkmnp-tv-z]{26}$/', $code)) {
            throw new TenantMissingException($code);
        }

        $cacheKey = 'platform:tenant:code:'.$code;

        $tenantId = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): ?string => Tenant::query()->where('id', $code)->value('id'),
        );

        if ($tenantId === null) {
            Cache::forget($cacheKey);

            throw new TenantMissingException($code);
        }

        $tenant = TenantHydrator::find($tenantId);

        if ($tenant === null) {
            Cache::forget($cacheKey);

            throw new TenantMissingException($code);
        }

        return TenantData::fromTenant($tenant);
    }
}
