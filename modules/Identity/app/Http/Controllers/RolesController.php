<?php

namespace Modules\Identity\App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantRoles;

/**
 * Read-only list of the roles that exist in the current school, with the
 * permissions each one carries and how many accounts hold it. Roles are
 * assigned per user on the user edit page; this page only explains them.
 *
 * Gated by the same permission as the user list (identity.users.view).
 */
final class RolesController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantRoles $roles,
    ) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        /** @var array<string, array{label: string, description?: string, permissions: list<string>}> $definitions */
        $definitions = config('roles');

        /** @var array<string, string> $permissionLabels */
        $permissionLabels = config('permission_labels');

        $roles = collect($this->roles->rolePermissions($this->context->currentOrFail()->id))
            ->map(fn (array $permissions, string $name): array => [
                'name' => $name,
                'label' => $definitions[$name]['label'] ?? $name,
                'description' => $definitions[$name]['description'] ?? null,
                'userCount' => User::query()
                    ->whereHas('roles', fn ($query) => $query->where('name', $name))
                    ->count(),
                'permissions' => collect($permissions)
                    ->map(fn (string $permission): string => $permissionLabels[$permission] ?? $permission)
                    ->values()
                    ->all(),
            ])
            ->sortBy('label')
            ->values()
            ->all();

        return Inertia::render('Identity/System/Roles', ['roles' => $roles]);
    }
}
