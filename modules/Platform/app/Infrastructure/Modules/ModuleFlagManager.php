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
     *
     * $source says who set the flag: `plan` (subscription sync, default)
     * never overwrites a flag the provider set by hand (`manual`).
     */
    public function enable(string $tenantId, string $module, ?\DateTimeInterface $expiresAt = null, string $source = TenantModulesModel::SOURCE_PLAN): void
    {
        $this->assertKnown($module);

        $this->context->run($tenantId, function () use ($module, $expiresAt, $source): void {
            if ($this->isHeldManually($module, $source)) {
                return;
            }

            TenantModulesModel::query()->updateOrCreate(
                ['module' => $module],
                ['enabled' => true, 'enabled_at' => now(), 'expires_at' => $expiresAt, 'source' => $source],
            );
        });

        $this->cache->forget($tenantId, $module);
    }

    /**
     * Disable a module for a tenant. A manual disable leaves a row behind
     * so later plan syncs know not to switch the module back on.
     */
    public function disable(string $tenantId, string $module, string $source = TenantModulesModel::SOURCE_PLAN): void
    {
        $this->assertKnown($module);

        $this->context->run($tenantId, function () use ($module, $source): void {
            if ($this->isHeldManually($module, $source)) {
                return;
            }

            if ($source === TenantModulesModel::SOURCE_MANUAL) {
                TenantModulesModel::query()->updateOrCreate(
                    ['module' => $module],
                    ['enabled' => false, 'expires_at' => null, 'source' => $source],
                );

                return;
            }

            TenantModulesModel::query()
                ->where('module', $module)
                ->update(['enabled' => false, 'expires_at' => null]);
        });

        $this->cache->forget($tenantId, $module);
    }

    /**
     * Must run inside the tenant context. Only a plan-sourced write is
     * blocked by a manual flag.
     */
    private function isHeldManually(string $module, string $source): bool
    {
        return $source === TenantModulesModel::SOURCE_PLAN
            && TenantModulesModel::query()
                ->where('module', $module)
                ->where('source', TenantModulesModel::SOURCE_MANUAL)
                ->exists();
    }

    private function assertKnown(string $module): void
    {
        if (! $this->registry->exists($module)) {
            throw new UnknownModuleException("Module [{$module}] is not registered.");
        }
    }
}
