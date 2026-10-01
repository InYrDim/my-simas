<?php

namespace Modules\Identity\App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Contracts\TenantRoles;

/**
 * Read-only catalogue of the permissions that exist in the current school,
 * grouped by the module that registered them, with which roles hold each.
 * Only modules enabled for the school are listed.
 *
 * Gated by the same permission as the user list (identity.users.view).
 */
final class PermissionsController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantRoles $roles,
        private readonly PermissionRegistry $permissions,
        private readonly ModuleRegistry $modules,
        private readonly TenantModules $enabled,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        /** @var array<string, array{label: string}> $definitions */
        $definitions = config('roles');

        /** @var array<string, string> $permissionLabels */
        $permissionLabels = config('permission_labels');

        $held = $this->roles->rolePermissions($this->context->currentOrFail()->id);
        $moduleMeta = $this->modules->all();

        $roles = collect($held)
            ->map(fn (array $permissions, string $name): array => [
                'name' => $name,
                'label' => $definitions[$name]['label'] ?? $name,
            ])
            ->sortBy('label')
            ->values();

        $groups = collect($this->permissions->all())
            ->filter(fn (array $names, string $module): bool => $names !== [] && $this->enabled->isEnabled($module))
            ->map(fn (array $names, string $module): array => [
                'module' => $module,
                'label' => $moduleMeta[$module]['label'] ?? $module,
                'permissions' => collect($names)
                    ->map(fn (string $permission): array => [
                        'name' => $permission,
                        'label' => $permissionLabels[$permission] ?? $permission,
                        'roles' => $roles
                            ->filter(fn (array $role): bool => in_array($permission, $held[$role['name']], true))
                            ->pluck('name')
                            ->values()
                            ->all(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Identity/System/Permissions', [
            'roles' => $roles->all(),
            'groups' => $groups,
        ]);
    }
}
