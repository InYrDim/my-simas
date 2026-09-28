<?php

namespace Modules\Platform\App\Infrastructure\Modules;

use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Domain\Models\TenantModules as TenantModulesModel;

/**
 * Read path for per-tenant module flags: always-active modules pass
 * without touching the DB; everything else is a cached row lookup.
 * Fail closed — unknown keys and missing rows are "not enabled".
 */
final class DefaultTenantModules implements TenantModules
{
    public function __construct(
        private readonly DefaultModuleRegistry $registry,
        private readonly TenantModulesCache $cache,
    ) {}

    public function isEnabled(string $module, ?string $tenantId = null): bool
    {
        // Always-active modules (core) pass even on central hosts —
        // no tenant id needed, the flag lookup is skipped entirely.
        if ($this->registry->isAlwaysActive($module)) {
            return true;
        }

        if (! $this->registry->exists($module)) {
            return false;
        }

        // Only flag-controlled modules need a tenant id; resolving it
        // lazily keeps central/CLI core lookups context-free.
        $tenantId ??= $this->currentTenantId();

        $cached = $this->cache->get($tenantId, $module);

        if ($cached !== null) {
            return $cached;
        }

        $enabled = $this->resolveFromDatabase($tenantId, $module);

        $this->cache->put($tenantId, $module, $enabled);

        return $enabled;
    }

    private function resolveFromDatabase(string $tenantId, string $module): bool
    {
        // Central data lookup: the tenant_modules table is scoped BY
        // tenant_id column, not by ambient context, so withoutTenancy()
        // keeps flag checks working from central/CLI code too.
        $row = TenantModulesModel::withoutTenancy()
            ->where('tenant_id', $tenantId)
            ->where('module', $module)
            ->first();

        if ($row === null || ! $row->enabled) {
            return false;
        }

        return $row->expires_at === null || $row->expires_at->isFuture();
    }

    private function currentTenantId(): string
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId ?? throw new TenantNotSetException(
            'No tenant context and no tenant id given for module flag lookup.',
        );
    }
}
