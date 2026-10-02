<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Platform\App\Contracts\TenantRoles;
use Modules\Platform\Database\Factories\TenantFactory;

/*
 * roles:sync — schools that existed before a role or permission was added
 * to config/roles.php get it without being recreated.
 */

/**
 * @return array<string, array<int, string>>
 */
function rolesOf(string $tenantId): array
{
    return app(TenantRoles::class)->rolePermissions($tenantId);
}

it('gives a new school the siswa role with only its own QR permission', function () {
    $tenant = TenantFactory::new()->create();

    expect(rolesOf($tenant->id))->toHaveKey('siswa')
        ->and(rolesOf($tenant->id)['siswa'])->toBe(['attendance.qr.show']);
});

it('adds a role and a permission that came after the school was created', function () {
    $tenant = TenantFactory::new()->create();

    config([
        'roles.guru.permissions' => ['core.master.view', 'core.academic.view', 'identity.users.view'],
        'roles.pustakawan' => ['label' => 'Pustakawan', 'permissions' => ['core.master.view']],
    ]);

    $this->artisan('roles:sync')->assertSuccessful();

    expect(rolesOf($tenant->id)['guru'])->toBe(['core.academic.view', 'core.master.view', 'identity.users.view'])
        ->and(rolesOf($tenant->id)['pustakawan'])->toBe(['core.master.view']);
});

it('changes nothing when it runs again', function () {
    $tenant = TenantFactory::new()->create();

    $this->artisan('roles:sync')->assertSuccessful();
    $first = rolesOf($tenant->id);

    $this->artisan('roles:sync')->assertSuccessful();

    expect(rolesOf($tenant->id))->toBe($first)
        ->and(DB::table('roles')->where('tenant_id', $tenant->id)->count())->toBe(count(config('roles')));
});

it('touches only the school named with --tenant', function () {
    $a = TenantFactory::new()->create(['slug' => 'sekolah-a']);
    $b = TenantFactory::new()->create(['slug' => 'sekolah-b']);

    config(['roles.pustakawan' => ['label' => 'Pustakawan', 'permissions' => []]]);

    $this->artisan('roles:sync', ['--tenant' => 'sekolah-a'])->assertSuccessful();

    expect(rolesOf($a->id))->toHaveKey('pustakawan')
        ->and(rolesOf($b->id))->not->toHaveKey('pustakawan');
});

it('fails for a school that does not exist', function () {
    $this->artisan('roles:sync', ['--tenant' => 'tidak-ada'])->assertFailed();
});
