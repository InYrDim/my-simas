<?php

namespace Modules\Identity\App\Infrastructure\Commands;

use Illuminate\Console\Command;
use Modules\Platform\App\Contracts\TenantDirectory;
use Modules\Platform\App\Contracts\TenantRoles;

/**
 * Brings the default roles of schools that already exist in line with
 * config/roles.php. A new school gets them from SeedDefaultRoles; this is
 * the same call for the schools created before a role or permission was
 * added.
 */
class RolesSyncCommand extends Command
{
    protected $signature = 'roles:sync {--tenant= : Only this tenant slug}';

    protected $description = 'Give every school the default roles and their permissions (idempotent)';

    public function handle(TenantDirectory $directory, TenantRoles $roles): int
    {
        $slug = $this->option('tenant');
        $tenants = $directory->all();

        if ($slug !== null) {
            $tenants = array_values(array_filter($tenants, fn ($tenant): bool => $tenant->slug === $slug));

            if ($tenants === []) {
                $this->error("Tenant [{$slug}] not found.");

                return self::FAILURE;
            }
        }

        /** @var array<string, array{permissions?: list<string>}> $definitions */
        $definitions = config('roles', []);

        foreach ($tenants as $tenant) {
            foreach ($definitions as $name => $definition) {
                $roles->ensure($tenant->id, $name, $definition['permissions'] ?? []);
            }
        }

        $this->info(sprintf('Synced %d role(s) for %d school(s).', count($definitions), count($tenants)));

        return self::SUCCESS;
    }
}
