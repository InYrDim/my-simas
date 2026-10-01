<?php

namespace Modules\Platform\App\Contracts;

/**
 * Builds the public URLs for a tenant — what password reset /
 * set-password links must point at.
 *
 * Needed because these emails are built INSIDE the queue, where the
 * request root cannot be trusted (a worker has no request at all).
 * Tenants share the central host: a link carries the school code as a
 * `school` query parameter, which the tenant resolver turns back into
 * the tenant. Scheme and port are configurable so dev hosts like
 * `localhost:8000` work without hardcoding environments.
 */
interface TenantUrl
{
    /**
     * The host tenant links point at (first central domain).
     */
    public function host(): string;

    /**
     * The scheme ("http"/"https"). Defaults from
     * config('tenancy.url_scheme').
     */
    public function scheme(): string;

    /**
     * The port appended to the host ("" in production, ":8000" in dev).
     * Defaults from config('tenancy.url_port').
     */
    public function port(): string;

    /**
     * Root URL: scheme + host + port, no trailing slash.
     */
    public function root(): string;

    /**
     * Full URL for a tenant route path, carrying the school code so the
     * link resolves the tenant on arrival. Throws when the tenant does
     * not exist.
     *
     * @param  array<string, string>  $query  extra query parameters
     */
    public function url(string $tenantId, string $path, array $query = []): string;
}
