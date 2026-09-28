<?php

namespace Modules\Platform\App\Infrastructure\Modules;

use Modules\Platform\App\Contracts\Exceptions\UnknownModuleException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\TenantModules as TenantModulesModel;

/**
 * Write path for per-tenant module flags. Kept internal (no contract):
 * consumers read via TenantModules; enable/disable happens through
 * Platform's own commands (Stage 7: tenant:modules).
 */
final class ModuleFlagManager
{
    public function __construct(
        private readonly DefaultModuleRegistry $registry,
        private readonly TenantModulesCache $cache,
        private readonly TenantContext $context,
    ) {}

    /**
     * Enable a module for a tenant. Optional expiry (e.g. trials).
     * Runs inside the given tenant context without disturbing the
     * caller's own context.
     */
    public function enable(string $tenantId, string $module, ?\DateTimeInterface $expiresAt = null): void
    {
        $this->assertKnown($module);

        $this->context->run($tenantId, function () use ($module, $expiresAt): void {
            TenantModulesModel::query()->updateOrCreate(
                ['module' => $module],
                ['enabled' => true, 'enabled_at' => now(), 'expires_at' => $expiresAt],
            );
        });

        $this->cache->forget($tenantId, $module);
    }

    /**
     * Disable a module for a tenant.
     */
    public function disable(string $tenantId, string $module): void
    {
        $this->assertKnown($module);

        $this->context->run($tenantId, function () use ($module): void {
            TenantModulesModel::query()
                ->where('module', $module)
                ->update(['enabled' => false, 'expires_at' => null]);
        });

        $this->cache->forget($tenantId, $module);
    }

    private function assertKnown(string $module): void
    {
        if (! $this->registry->exists($module)) {
            throw new UnknownModuleException("Module [{$module}] is not registered.");
        }
    }
}
