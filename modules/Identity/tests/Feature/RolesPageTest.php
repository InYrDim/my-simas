<?php

namespace Modules\Identity\Tests\Feature;

use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;

/**
 * Peran: the read-only list of the roles a school has, their permissions
 * and how many accounts hold each.
 */
function rolesTenant(string $slug): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);
    app(ModuleFlagManager::class)->enable($tenant->id, 'identity');

    return $tenant;
}

function rolesUser(Tenant $tenant, string $email, string $role): User
{
    $user = User::factory()->forTenant($tenant->id)->create(['email' => $email]);

    app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole($role));

    return $user;
}

beforeEach(function () {
    User::flushTenantPermissionCache();
});

it('shows the school admin the roles of the tenant with permissions and user counts', function () {
    $tenant = rolesTenant('roles-a');
    $admin = rolesUser($tenant, 'admin@roles-a.test', 'admin-sekolah');
    rolesUser($tenant, 'guru1@roles-a.test', 'guru');
    rolesUser($tenant, 'guru2@roles-a.test', 'guru');

    actingAs($admin)
        ->get(school('roles-a', '/sistem/peran'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/System/Roles')
            ->has('roles', 3)
            ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'guru')['userCount'] === 2
                && collect($roles)->firstWhere('name', 'admin-sekolah')['label'] === 'Admin Sekolah'
                && in_array('Melihat daftar pengguna', collect($roles)->firstWhere('name', 'admin-sekolah')['permissions'], true)
                && collect($roles)->firstWhere('name', 'staf-tu')['permissions'] === [])
        );
});

it('keeps the role list from another tenant out', function () {
    $tenant = rolesTenant('roles-b');
    $other = rolesTenant('roles-c');
    $admin = rolesUser($tenant, 'admin@roles-b.test', 'admin-sekolah');
    rolesUser($other, 'guru@roles-c.test', 'guru');

    actingAs($admin)
        ->get(school('roles-b', '/sistem/peran'))
        ->assertInertia(fn ($page) => $page
            ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'guru')['userCount'] === 0)
        );
});

it('forbids a teacher from the role list', function () {
    $tenant = rolesTenant('roles-d');
    $guru = rolesUser($tenant, 'guru@roles-d.test', 'guru');

    actingAs($guru)->get(school('roles-d', '/sistem/peran'))->assertForbidden();
});

it('lists the permissions of enabled modules with the roles that hold them', function () {
    $tenant = rolesTenant('roles-e');
    $admin = rolesUser($tenant, 'admin@roles-e.test', 'admin-sekolah');

    actingAs($admin)
        ->get(school('roles-e', '/sistem/izin'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/System/Permissions')
            ->has('roles', 3)
            ->where('groups', fn ($groups) => collect($groups)->pluck('module')->all() === ['identity']
                && collect($groups[0]['permissions'])->firstWhere('name', 'identity.users.view')['roles'] === ['admin-sekolah']
                && collect($groups[0]['permissions'])->firstWhere('name', 'identity.users.view')['label'] === 'Melihat daftar pengguna')
        );
});

it('forbids a teacher from the permission list', function () {
    $tenant = rolesTenant('roles-f');
    $guru = rolesUser($tenant, 'guru@roles-f.test', 'guru');

    actingAs($guru)->get(school('roles-f', '/sistem/izin'))->assertForbidden();
});
