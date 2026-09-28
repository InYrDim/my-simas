<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Support\Facades\Cache;
use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Domain\Models\Tenant;

/**
 * The one and only tenant resolution strategy.
 *
 * Host normalization: lowercase, strip trailing port and dot.
 *
 * - Host ∈ config('tenancy.central_domains')          → null (no tenant)
 * - Host = "{slug}.{central_domain}" (one level only) → lookup by slug
 * - Anything else                                     → lookup by domain
 *
 * "{a}.{b}.{central}" is NOT a slug host (multi-level subdomains are not
 * provisioned); it falls through to the custom-domain lookup. Suspended
 * tenants still resolve — the middleware decides 403 (existence must not
 * leak as 404 vs 403 asymmetry is intentional: known host = 403).
 */
final class SubdomainTenantResolver implements TenantResolver
{
    /**
     * Cache TTL (seconds) for host → tenant id lookups.
     */
    private const CACHE_TTL = 300;

    public function resolve(string $host): ?TenantData
    {
        $host = $this->normalize($host);

        if (in_array($host, config('tenancy.central_domains', []), true)) {
            return null;
        }

        $slugHostSuffixes = array_map(
            fn (string $central): string => '.'.$central,
            config('tenancy.central_domains', []),
        );

        foreach ($slugHostSuffixes as $suffix) {
            if (str_ends_with($host, $suffix)) {
                $slug = substr($host, 0, -strlen($suffix));

                // Exactly one level of subdomain: no further dot allowed.
                if ($slug === '' || str_contains($slug, '.')) {
                    throw new TenantMissingException($host);
                }

                return $this->findByCacheable('slug', $slug);
            }
        }

        return $this->findByCacheable('domain', $host);
    }

    /**
     * Normalize a host: lowercase, strip port and trailing dot.
     */
    private function normalize(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return rtrim($host, '.');
    }

    /**
     * Cache host-attribute lookups so every request does not hit the
     * tenants table; entries are plain ids, re-fetched as DTOs.
     */
    private function findByCacheable(string $attribute, string $value): TenantData
    {
        $cacheKey = "platform:tenant:{$attribute}:".md5($value);

        $tenantId = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): ?string => Tenant::query()
                ->where($attribute, $value)
                ->value('id'),
        );

        if ($tenantId === null) {
            throw new TenantMissingException($value);
        }

        $tenant = TenantHydrator::find($tenantId);

        if ($tenant === null) {
            Cache::forget($cacheKey);

            throw new TenantMissingException($value);
        }

        return TenantData::fromTenant($tenant);
    }
}
