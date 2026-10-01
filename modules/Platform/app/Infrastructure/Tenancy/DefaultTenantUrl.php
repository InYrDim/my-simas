<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\TenantUrl;

/**
 * Default TenantUrl: every tenant shares the first central domain; the
 * tenant is identified by the `school` query parameter (the school
 * code, validated against the cached resolution data — no fresh
 * queries). scheme/port come from config so dev (`http`, `:8000`) and
 * production (`https`, ``) differ without code changes.
 */
final class DefaultTenantUrl implements TenantUrl
{
    public function host(): string
    {
        $central = (array) config('tenancy.central_domains', ['localhost']);

        return (string) ($central[0] ?? 'localhost');
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

    public function root(): string
    {
        return $this->scheme().'://'.$this->host().$this->port();
    }

    public function url(string $tenantId, string $path, array $query = []): string
    {
        $tenant = TenantHydrator::find($tenantId);

        if ($tenant === null) {
            throw new \InvalidArgumentException(
                "Cannot build tenant URL: tenant [{$tenantId}] does not exist.",
            );
        }

        return $this->root().'/'.ltrim($path, '/').'?'.http_build_query(
            [...$query, 'school' => $tenantId],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
    }
}
