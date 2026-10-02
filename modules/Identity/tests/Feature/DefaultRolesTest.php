<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\Events\TenantCreated;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantRoles;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

uses(RefreshDatabase::class);

/**
 * Stage 2 (Fase 2): default school roles seeded per tenant on
 * TenantCreated. Role names are machine names; labels live in config.
 *
 * NOTE: no Spatie classes here — arch tests ban Spatie imports outside
 * Platform (module tests are NOT exempt, unlike Deptrac's tests
 * exclusion). Assert via table queries instead.
 */

/**
 * @return array<int, string>
 */
function tenantRolePermissionNames(string $tenantId, string $name): array
{
    return DB::table('permissions')
        ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
        ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
        ->where('roles.tenant_id', $tenantId)
        ->where('roles.name', $name)
        ->orderBy('permissions.name')
        ->pluck('permissions.name')
        ->all();
}

it('seeds the default roles for a new tenant via TenantCreated', function () {
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create();

    // The Tenant model's created hook dispatches TenantCreated; the
    // Identity listener runs synchronously.
    $roles = app(TenantRoles::class)->names($tenant->id);

    expect($roles)->toContain('admin-sekolah')
        ->and($roles)->toContain('guru')
        ->and($roles)->toContain('staf-tu')
        ->and($roles)->toContain('siswa');
});

it('gives admin-sekolah the identity.users and core permission sets and the others only the core view permissions', function () {
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create();

    app(TenantRoles::class)->ensure($tenant->id, 'admin-sekolah', config('roles.admin-sekolah.permissions'));
    app(TenantRoles::class)->ensure($tenant->id, 'guru', config('roles.guru.permissions'));
    app(TenantRoles::class)->ensure($tenant->id, 'staf-tu', config('roles.staf-tu.permissions'));

    $admin = tenantRolePermissionNames($tenant->id, 'admin-sekolah');
    $guru = tenantRolePermissionNames($tenant->id, 'guru');
    $staf = tenantRolePermissionNames($tenant->id, 'staf-tu');

    expect($admin)->toBe([
        'core.academic.manage',
        'core.academic.view',
        'core.integration.manage',
        'core.master.manage',
        'core.master.view',
        'identity.users.create',
        'identity.users.deactivate',
        'identity.users.sendReset',
        'identity.users.update',
        'identity.users.view',
    ])->and($guru)->toBe(['core.academic.view', 'core.master.view'])
        ->and($staf)->toBe(['core.academic.view', 'core.master.view']);
});

it('creates the global permission rows as a side effect of seeding', function () {
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create();

    // ensure() runs PermissionSync internally, so the global permission
    // rows exist even when permissions:sync was never executed.
    foreach (config('roles.admin-sekolah.permissions') as $name) {
        expect(DB::table('permissions')->where('name', $name)->where('guard_name', 'web')->exists())->toBeTrue();
    }
});

it('keeps same-named roles of two tenants apart', function () {
    /** @var Tenant $a */
    $a = TenantFactory::new()->create();
    /** @var Tenant $b */
    $b = TenantFactory::new()->create();

    app(TenantRoles::class)->ensure($a->id, 'admin-sekolah', config('roles.admin-sekolah.permissions'));
    app(TenantRoles::class)->ensure($b->id, 'admin-sekolah', config('roles.admin-sekolah.permissions'));

    $rows = DB::table('roles')->where('name', 'admin-sekolah');

    expect((clone $rows)->where('tenant_id', $a->id)->exists())->toBeTrue()
        ->and((clone $rows)->where('tenant_id', $b->id)->exists())->toBeTrue()
        ->and($rows->count())->toBe(2);
});

it('is idempotent when TenantCreated fires again', function () {
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create();

    // Re-fire the event (same dispatch path as TenantEvents):
    // ensure() is create-or-update, never duplicates.
    Event::dispatch(new TenantCreated($tenant->id));
    Event::dispatch(new TenantCreated($tenant->id));

    expect(DB::table('roles')->where('tenant_id', $tenant->id)->where('name', 'admin-sekolah')->count())->toBe(1)
        ->and(DB::table('roles')->where('tenant_id', $tenant->id)->count())->toBe(4);
});

it('rejects ensure() for an unknown tenant id (fail closed)', function () {
    app(TenantRoles::class)->ensure('01UNKNOWNUNKNOWNUNKNOWNUNKNOWN', 'admin-sekolah');
})->throws(\InvalidArgumentException::class);

it('rejects names() for an unknown tenant id (fail closed)', function () {
    app(TenantRoles::class)->names('01UNKNOWNUNKNOWNUNKNOWNUNKNOWN');
})->throws(\InvalidArgumentException::class);

it('tenant:create seeds roles too (same TenantCreated pipeline)', function () {
    $this->artisan('tenant:create', [
        'name' => 'Sekolah Uji',
        'slug' => 'sekolah-uji',
        '--no-interaction' => true,
    ])->assertSuccessful();

    /** @var Tenant|null $tenant */
    $tenant = Tenant::query()->where('slug', 'sekolah-uji')->first();

    expect($tenant)->not->toBeNull()
        ->and(app(TenantRoles::class)->names($tenant->id))->toContain('admin-sekolah');
});

it('assigns the seeded admin role to a user within tenant context', function () {
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create();

    // Assign AND assert inside the context: Spatie's team pointer only
    // points at the tenant while the context is set (real requests
    // always run with ambient context).
    app(TenantContext::class)->run($tenant->id, function () use ($tenant): void {
        $user = User::factory()->forTenant($tenant->id)->create();
        $user->assignTenantRole('admin-sekolah');

        $fresh = $user->refresh();

        expect($fresh->hasTenantRole('admin-sekolah'))->toBeTrue()
            ->and($fresh->tenantRoleNames())->toContain('admin-sekolah');
    });
});
