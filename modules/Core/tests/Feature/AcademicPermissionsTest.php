<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

dataset('academicPages', [
    'placement' => ['/akademik/penempatan'],
    'assignments' => ['/akademik/pengampu'],
    'homerooms' => ['/akademik/wali-kelas'],
    'periods' => ['/akademik/jam-pelajaran'],
    'calendar' => ['/akademik/kalender'],
]);

it('lets every default school role view the academic pages', function (string $role) {
    $tenant = schoolAs("akademik-{$role}", $role);

    get(school($tenant->slug, '/akademik/jam-pelajaran'))->assertOk();
})->with(['admin-sekolah', 'guru', 'staf-tu']);

it('keeps the editor-only academic pages for roles that may manage', function (string $role, string $path, bool $allowed) {
    $tenant = schoolAs("editor-{$role}", $role);

    $response = get(school($tenant->slug, $path));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with(function () {
    foreach (['/akademik/penempatan', '/akademik/pengampu', '/akademik/wali-kelas'] as $path) {
        yield "admin {$path}" => ['admin-sekolah', $path, true];
        yield "teacher {$path}" => ['guru', $path, false];
    }
});

it('refuses a signed-in user without the academic view permission', function (string $path) {
    $tenant = TenantFactory::new()->create(['slug' => 'akademik-none']);

    actingAs(UserFactory::new()->forTenant($tenant->id)->create(['email' => 'user@akademik-none.test']));

    get(school($tenant->slug, $path))->assertForbidden();
})->with('academicPages');

it('hides the Akademik sidebar entry from a user without the permission', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'akademik-nav']);

    actingAs(UserFactory::new()->forTenant($tenant->id)->create(['email' => 'user@akademik-nav.test']));

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => ! collect($nav)->pluck('label')->contains('Akademik'))
    );
});

it('sends guests to the login', function (string $path) {
    $tenant = TenantFactory::new()->create(['slug' => 'akademik-guest']);

    get(school($tenant->slug, $path))->assertRedirect();
})->with('academicPages');
