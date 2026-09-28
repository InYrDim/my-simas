<?php

namespace Modules\Platform\App\Contracts;

/**
 * Per-tenant feature flags for registered module keys. Read path for
 * the middleware: cached via TenantCache and invalidated whenever the
 * flag row changes.
 */
interface TenantModules
{
    /**
     * Whether the module is enabled for the given tenant (default:
     * current tenant). Always true for always-active modules (e.g.
     * core). Unknown keys are never enabled. Respects expires_at.
     *
     * @throws Exceptions\TenantNotSetException when no tenant id is
     *                                          given and no tenant context is set.
     */
    public function isEnabled(string $module, ?string $tenantId = null): bool;
}
