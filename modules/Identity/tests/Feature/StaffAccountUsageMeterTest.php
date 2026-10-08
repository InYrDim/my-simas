<?php

namespace Modules\Identity\Tests\Feature;

use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantUsage;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

/**
 * The `staff_accounts` usage meter: active users who do not hold `siswa`.
 */
function staffUsageTenant(string $slug): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);
    app(ModuleFlagManager::class)->enable($tenant->id, 'identity');

    return $tenant;
}

/**
 * @param  list<string>  $roles
 */
function staffUsageUser(Tenant $tenant, array $roles = [], string $state = 'active'): User
{
    $factory = User::factory()->forTenant($tenant->id);
    $user = match ($state) {
        'deactivated' => $factory->deactivated()->create(),
        'invited' => $factory->invited()->create(),
        default => $factory->create(),
    };

    app(TenantContext::class)->run($tenant->id, function () use ($user, $roles): void {
        foreach ($roles as $role) {
            $user->assignTenantRole($role);
        }
    });

    return $user;
}

function staffUsageOf(Tenant $tenant): ?object
{
    User::flushTenantPermissionCache();

    return collect(app(TenantUsage::class)->forTenant($tenant->id))->firstWhere('key', 'staff_accounts');
}

it('counts staff, invited accounts and users who hold siswa together with another role', function () {
    $tenant = staffUsageTenant('staff-count');
    staffUsageUser($tenant, ['admin-sekolah']);
    staffUsageUser($tenant, ['guru']);
    staffUsageUser($tenant, ['guru'], 'invited');
    staffUsageUser($tenant, ['siswa', 'guru']);
    staffUsageUser($tenant);

    expect(staffUsageOf($tenant)->used)->toBe(5);
});

it('leaves out students and deactivated accounts', function () {
    $tenant = staffUsageTenant('staff-skip');
    staffUsageUser($tenant, ['admin-sekolah']);
    staffUsageUser($tenant, ['siswa']);
    staffUsageUser($tenant, ['siswa']);
    staffUsageUser($tenant, ['guru'], 'deactivated');

    expect(staffUsageOf($tenant)->used)->toBe(1);
});

it('never counts the accounts of another school', function () {
    $a = staffUsageTenant('staff-a');
    $b = staffUsageTenant('staff-b');
    staffUsageUser($a, ['guru']);
    staffUsageUser($b, ['guru']);
    staffUsageUser($b, ['guru']);
    staffUsageUser($b, ['siswa']);

    expect(staffUsageOf($a)->used)->toBe(1)
        ->and(staffUsageOf($b)->used)->toBe(2);
});

it('hides the meter while the identity module is off for the school', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'staff-off']);
    staffUsageUser($tenant, ['guru']);

    expect(staffUsageOf($tenant))->toBeNull();
});
