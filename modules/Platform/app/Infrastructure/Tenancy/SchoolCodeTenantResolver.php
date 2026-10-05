<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Support\Facades\Cache;
use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Domain\Models\Tenant;

/**
 * The one and only tenant resolution strategy: by school code.
 *
 * The school code is the tenant id (ULID) or the tenant's unique slug
 * (the readable form used in `/{slug}/login`); it may become the NPSN
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

    /**
     * Drop the cached code → tenant lookup (the code was changed).
     */
    public static function forget(string $code): void
    {
        Cache::forget('platform:tenant:code:'.strtolower(trim($code)));
    }

    public function resolve(string $code): TenantData
    {
        $code = strtolower(trim($code));

        $isId = (bool) preg_match('/^[0-9a-hjkmnp-tv-z]{26}$/', $code);

        if (! $isId && ! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $code)) {
            throw new TenantMissingException($code);
        }

        $cacheKey = 'platform:tenant:code:'.$code;

        $tenantId = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): ?string => ($isId ? Tenant::query()->where('id', $code)->value('id') : null)
                ?? Tenant::query()->where('slug', $code)->value('id'),
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
