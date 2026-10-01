<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantNavigation;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * The school-side sidebar is fed by Platform's TenantNavigation
 * registry: each module registers its own entries, and the shared
 * `tenantNav` prop is filtered by active module and Gate.
 */
function navTenantAs(string $slug, string $role, bool $identity = true): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    if ($identity) {
        app(ModuleFlagManager::class)->enable($tenant->id, 'identity');
    }

    $user = UserFactory::new()->forTenant($tenant->id)->create([
        'email' => "{$role}@{$slug}.test",
    ]);

    app(TenantContext::class)->run(
        $tenant->id,
        fn () => $user->assignTenantRole($role),
    );

    actingAs($user);

    return $tenant;
}

it('shares the sidebar entries an admin may see, in order', function () {
    $tenant = navTenantAs('nav-admin', 'admin-sekolah');

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->all() === ['Beranda', 'Master Data', 'Akademik', 'Impor Data', 'Statistik & Laporan', 'Pengguna', 'Integrasi', 'Sistem'])
        ->where('tenantNav.0.href', '/beranda')
        ->where('tenantNav.0.icon', 'layout-dashboard')
    );
});

it('hides entries the user lacks the permission for', function () {
    $tenant = navTenantAs('nav-guru', 'guru');

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->all() === ['Beranda', 'Master Data', 'Akademik', 'Impor Data', 'Statistik & Laporan', 'Integrasi'])
    );
});

it('hides entries of modules the tenant has not enabled', function () {
    $tenant = navTenantAs('nav-no-identity', 'admin-sekolah', identity: false);

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->all() === ['Beranda', 'Master Data', 'Akademik', 'Impor Data', 'Statistik & Laporan', 'Integrasi'])
    );
});

it('nests visible children and drops a group with none visible', function () {
    app(TenantNavigation::class)->register('core', [
        [
            'label' => 'Grup',
            'icon' => 'folder',
            'route' => 'password.request',
            'order' => 45,
            'children' => [
                ['label' => 'Pengguna', 'route' => 'identity.users.index', 'permission' => 'identity.users.view'],
            ],
        ],
    ]);

    $admin = navTenantAs('nav-group-admin', 'admin-sekolah');

    get(school($admin->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav.5.label', 'Grup')
        ->where('tenantNav.5.children.0.href', '/users')
    );

    $guru = navTenantAs('nav-group-guru', 'guru');

    get(school($guru->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->all() === ['Beranda', 'Master Data', 'Akademik', 'Impor Data', 'Statistik & Laporan', 'Integrasi'])
    );
});
