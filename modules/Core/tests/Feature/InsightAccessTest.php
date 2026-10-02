<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Statistik & Laporan hold student records, so the pages are behind
 * core.master.view — every default role has it, a user without a role
 * does not.
 */
dataset('insightPages', [
    'statistics' => ['/statistik-laporan/statistik'],
    'reports' => ['/statistik-laporan/laporan'],
]);

it('opens the page for every default role', function (string $role, string $path) {
    $tenant = schoolAs("insight-{$role}", $role);

    get(school($tenant->slug, $path))->assertOk();
})->with(['admin-sekolah', 'guru', 'staf-tu'])->with('insightPages');

it('refuses a user who may not view master data', function (string $path) {
    $tenant = TenantFactory::new()->create(['slug' => 'insight-tanpa-peran']);
    actingAs(UserFactory::new()->forTenant($tenant->id)->create());

    get(school($tenant->slug, $path))->assertForbidden();
})->with('insightPages');

it('hides the sidebar entry from a user who may not view master data', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'insight-nav']);
    actingAs(UserFactory::new()->forTenant($tenant->id)->create());

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => ! collect($nav)->pluck('label')->contains('Statistik & Laporan'))
    );
});

it('sends guests to the login', function (string $path) {
    $tenant = TenantFactory::new()->create(['slug' => 'insight-guest']);

    get(school($tenant->slug, $path))->assertRedirect();
})->with('insightPages');
