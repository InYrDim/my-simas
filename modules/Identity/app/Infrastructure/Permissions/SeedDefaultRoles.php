<?php

namespace Modules\Identity\App\Infrastructure\Permissions;

use Modules\Platform\App\Contracts\Events\TenantCreated;
use Modules\Platform\App\Contracts\TenantRoles;

/**
 * Seeds the tenant's default school roles the moment the tenant exists.
 * Listens to Platform's TenantCreated (fired from the Tenant model's
 * created hook — runs in factory/CLI/central contexts WITHOUT ambient
 * tenant context, which is exactly why the TenantRoles contract takes
 * an explicit tenant id).
 *
 * Idempotent: re-firing the event converges (ensure() is
 * create-or-update, never duplicates). Role machine names and their
 * permission sets live in modules/Identity/config/roles.php.
 */
final class SeedDefaultRoles
{
    public function __construct(
        private readonly TenantRoles $roles,
    ) {}

    public function handle(TenantCreated $event): void
    {
        foreach (config('roles') as $name => $definition) {
            $this->roles->ensure(
                $event->tenantId,
                $name,
                $definition['permissions'] ?? [],
            );
        }
    }
}
