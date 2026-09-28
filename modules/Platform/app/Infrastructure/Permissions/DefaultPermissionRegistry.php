<?php

namespace Modules\Platform\App\Infrastructure\Permissions;

use Modules\Platform\App\Contracts\PermissionRegistry;

/**
 * In-memory registry of permission names, populated by each module's
 * service provider at boot. Materialisation into the permissions table
 * happens via `permissions:sync` (Stage 7), never at boot.
 */
final class DefaultPermissionRegistry implements PermissionRegistry
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $permissions = [];

    public function register(string $module, array $permissions): void
    {
        // Idempotent + de-duplicated across providers/boots.
        $existing = $this->permissions[$module] ?? [];

        $this->permissions[$module] = array_values(array_unique([
            ...$existing,
            ...$permissions,
        ]));
    }

    public function all(): array
    {
        return $this->permissions;
    }

    public function forModule(string $module): array
    {
        return $this->permissions[$module] ?? [];
    }
}
