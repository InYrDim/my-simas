<?php

namespace Modules\Core\Tests\Feature;

use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function masterUserWithRole(string $slug, ?string $role): string
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);
    $user = UserFactory::new()->forTenant($tenant->id)->create(['email' => "user@{$slug}.test"]);

    if ($role !== null) {
        app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole($role));
    }

    actingAs($user);

    return $tenant->slug;
}

it('registers the core master, academic and integration permissions', function () {
    expect(app(PermissionRegistry::class)->forModule('core'))
        ->toBe(['core.master.view', 'core.master.manage', 'core.academic.view', 'core.academic.manage', 'core.integration.manage', 'core.me.view', 'core.teaching.view']);
});

it('lets every default school role view master data', function (string $role) {
    $slug = masterUserWithRole("perm-{$role}", $role);

    get(school($slug, '/master/ruangan'))->assertOk();
})->with(['admin-sekolah', 'staf-tu']);

it('keeps master data from a teacher, whose menu is Jadwal Saya and Kelas Mengajar', function () {
    $slug = masterUserWithRole('perm-guru', 'guru');

    get(school($slug, '/master/ruangan'))->assertForbidden();
});

it('keeps the school profile form for roles that may manage master data', function (string $role, bool $allowed) {
    $slug = masterUserWithRole("profile-{$role}", $role);

    $response = get(school($slug, '/master/sekolah'));

    $allowed ? $response->assertOk() : $response->assertForbidden();
})->with([
    'admin' => ['admin-sekolah', true],
    'teacher' => ['guru', false],
    'staff' => ['staf-tu', false],
]);

it('refuses a signed-in user without the view permission', function () {
    $slug = masterUserWithRole('perm-none', null);

    get(school($slug, '/master/ruangan'))->assertForbidden();
});

it('sends guests to the login', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'perm-guest']);

    get(school($tenant->slug, '/master/sekolah'))->assertRedirect();
});
