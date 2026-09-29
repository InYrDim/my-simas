<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\TenantUrl;

/**
 * Default TenantUrl: derives the tenant host from the cached resolution
 * data (TenantHydrator attribute arrays — no fresh queries) and the
 * central domain from config. Mirrors SubdomainTenantResolver's
 * "exactly one subdomain level" convention:
 *
 * - tenant has a custom `domain` → that domain
 * - otherwise → {slug}.{first central domain}
 *
 * scheme/port come from config so dev (`http`, `:8000`) and production
 * (`https`, ``) differ without code changes.
 */
final class DefaultTenantUrl implements TenantUrl
{
    public function host(string $tenantId): string
    {
        $tenant = TenantHydrator::find($tenantId);

        if ($tenant === null) {
            throw new \InvalidArgumentException(
                "Cannot build tenant URL: tenant [{$tenantId}] does not exist.",
            );
        }

        $domain = $tenant->getAttribute('domain');

        if (is_string($domain) && $domain !== '') {
            return $domain;
        }

        $central = (array) config('tenancy.central_domains', ['localhost']);

        $base = $central[0] ?? 'localhost';

        return $tenant->getAttribute('slug').'.'.$base;
    }

    public function scheme(): string
    {
        return (string) config('tenancy.url_scheme', 'http');
    }

    public function port(): string
    {
        $port = (string) config('tenancy.url_port', '');

        return $port === '' ? '' : ':'.$port;
    }

    public function root(string $tenantId): string
    {
        return $this->scheme().'://'.$this->host($tenantId).$this->port();
    }
}
