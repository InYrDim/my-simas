<?php

namespace Modules\Platform\App\Infrastructure\Permissions;

use Modules\Platform\App\Contracts\TenantContext;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Materialises registered permission names into Spatie's permissions
 * table. Idempotent: create-if-missing, never revokes or renames.
 *
 * Permissions are GLOBAL rows (Spatie semantics): access control is
 * decided by the tenant assignment on the pivot (model_has_permissions
 * / model_has_roles carry the required tenant_id), not by the
 * permission definition itself. Sync therefore runs without tenant
 * context and must not be affected by (or disturb) the caller's
 * ambient context.
 */
final class PermissionSync
{
    public function __construct(
        private readonly DefaultPermissionRegistry $registry,
        private readonly TenantContext $context,
    ) {}

    /**
     * Create all registered permissions that do not exist yet.
     *
     * @return array<int, string> names that were created (for command output)
     */
    public function sync(): array
    {
        $created = [];

        $all = $this->registry->all();

        $this->context->runWithoutTenant(function () use (&$created, $all): void {
            foreach ($all as $permissions) {
                $created = [...$created, ...$this->createMissing($permissions)];
            }
        });

        if ($created !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $created;
    }

    /**
     * Create the given permission names if missing (create-if-missing,
     * never revokes). Context-free by design — global rows, ambient
     * context restored afterwards.
     *
     * @param  array<int, string>  $permissions
     */
    public function ensurePermissions(array $permissions): void
    {
        $created = $this->context->runWithoutTenant(
            fn (): array => $this->createMissing($permissions),
        );

        if ($created !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    /**
     * Insert missing permission rows; returns the created names.
     *
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    private function createMissing(array $permissions): array
    {
        $created = [];

        foreach ($permissions as $name) {
            $exists = Permission::query()
                ->where('name', $name)
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                Permission::query()->create(['name' => $name, 'guard_name' => 'web']);
                $created[] = $name;
            }
        }

        return $created;
    }
}
