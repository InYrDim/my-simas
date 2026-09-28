<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\TenantData;

/**
 * Internal — other modules must not bind or replace this; middleware
 * only calls it. One strategy: central domains return null, a
 * "{slug}.{central}" host resolves by slug, everything else by custom
 * domain.
 */
interface TenantResolver
{
    /**
     * Resolve the tenant for the given request host. Returns null when
     * the host is a central domain (request must run without tenant).
     *
     * @throws TenantMissingException when no tenant matches
     */
    public function resolve(string $host): ?TenantData;
}
