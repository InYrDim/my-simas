<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * PPDB mockup pages: every route renders for a signed-in user of a
 * tenant that has the module enabled, and is blocked without the flag.
 */
function ppdbTenant(bool $enabled = true): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $enabled ? 'ppdb-on' : 'ppdb-off']);

    if ($enabled) {
        app(ModuleFlagManager::class)->enable($tenant->id, 'ppdb');
    }

    $user = UserFactory::new()->forTenant($tenant->id)->create(['email' => "admin@{$tenant->slug}.test"]);

    app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole('admin-sekolah'));

    actingAs($user);

    return $tenant;
}

dataset('ppdbPages', [
    'overview' => ['/ppdb', 'Ppdb/Overview'],
    'applicants' => ['/ppdb/pendaftar', 'Ppdb/Applicants'],
    'selection' => ['/ppdb/seleksi', 'Ppdb/Selection'],
]);

it('renders each ppdb page for a school user', function (string $path, string $component) {
    $tenant = ppdbTenant();

    get(school($tenant->slug, $path))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component($component)
    );
})->with('ppdbPages');

it('lists the PPDB group in the sidebar when the module is enabled', function () {
    $tenant = ppdbTenant();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->contains('PPDB'))
    );
});

it('hides and blocks PPDB for a tenant without the module', function () {
    $tenant = ppdbTenant(enabled: false);

    get(school($tenant->slug, '/ppdb'))->assertForbidden();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => ! collect($nav)->pluck('label')->contains('PPDB'))
    );
});
