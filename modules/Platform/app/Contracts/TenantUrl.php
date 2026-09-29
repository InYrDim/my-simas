<?php

namespace Modules\Platform\App\Contracts;

/**
 * Resolves the public host for a tenant — the URL root that password
 * reset / set-password links must point at.
 *
 * Needed because these emails are built INSIDE the queue, where the
 * request root cannot be trusted (a worker has no request at all).
 * Scheme and port are configurable so dev hosts like
 * `sekolah-a.localhost:8000` work without hardcoding environments.
 */
interface TenantUrl
{
    /**
     * The tenant's host, e.g. "sekolah-a.simas.test" or a custom
     * domain. Throws when the tenant does not exist.
     */
    public function host(string $tenantId): string;

    /**
     * The scheme ("http"/"https") for tenant hosts. Defaults from
     * config('tenancy.url_scheme').
     */
    public function scheme(): string;

    /**
     * The port appended to the host ("" in production, ":8000" in dev).
     * Defaults from config('tenancy.url_port').
     */
    public function port(): string;

    /**
     * Full root URL for the tenant: scheme + host + port, no trailing
     * slash — prefix any tenant route path with it.
     */
    public function root(string $tenantId): string;
}
