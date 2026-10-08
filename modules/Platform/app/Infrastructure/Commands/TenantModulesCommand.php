<?php

namespace Modules\Platform\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantModules;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;

class TenantModulesCommand extends Command
{
    protected $signature = 'tenant:modules
        {tenant : Tenant slug}
        {--enable=* : Module keys to enable}
        {--disable=* : Module keys to disable}';

    protected $description = 'Enable or disable modules for a tenant';

    public function handle(
        ModuleRegistry $registry,
        ModuleFlagManager $flags,
    ): int {
        $slug = mb_strtolower(trim((string) $this->argument('tenant')));

        $tenantId = Tenant::query()
            ->where('slug', $slug)
            ->value('id');

        if ($tenantId === null) {
            $this->error("Tenant [{$slug}] not found.");

            return self::FAILURE;
        }

        $enable = (array) $this->option('enable');
        $disable = (array) $this->option('disable');

        foreach ([...$enable, ...$disable] as $module) {
            if (! $registry->exists($module)) {
                $this->error("Module [{$module}] is not registered. Registered: ".
                    implode(', ', array_keys($registry->all()) ?: ['(none)']));

                return self::FAILURE;
            }
        }

        foreach (array_intersect($enable, $disable) as $both) {
            $this->error("Module [{$both}] cannot be enabled and disabled at once.");

            return self::FAILURE;
        }

        foreach ($enable as $module) {
            $flags->enable($tenantId, $module, null, TenantModules::SOURCE_MANUAL);
            $this->info("Enabled [{$module}] for [{$slug}].");
        }

        foreach ($disable as $module) {
            $flags->disable($tenantId, $module, TenantModules::SOURCE_MANUAL);
            $this->info("Disabled [{$module}] for [{$slug}].");
        }

        if ($enable === [] && $disable === []) {
            $this->warn('Nothing to do: pass --enable= or --disable=.');
        }

        return self::SUCCESS;
    }
}
