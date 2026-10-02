<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Who may open which PPDB page: the pages follow the ppdb.* permissions of
 * the default roles, and the whole module follows the school's module flag.
 */

it('registers the ppdb permissions', function () {
    expect(app(PermissionRegistry::class)->forModule('ppdb'))->toBe([
        'ppdb.view',
        'ppdb.applicants.manage',
        'ppdb.selection.manage',
        'ppdb.settings.manage',
    ]);
});

it('opens the PPDB pages for the admin and the staff', function (string $role, string $path) {
    $tenant = ppdbTenant(role: $role, slug: "akses-{$role}");

    get(school($tenant->slug, $path))->assertOk();
})->with(['admin-sekolah', 'staf-tu'])->with(['/ppdb', '/ppdb/pendaftar', '/ppdb/seleksi']);

it('refuses teachers, students and users without a role on every PPDB page', function (?string $role, string $path) {
    $tenant = ppdbTenant(role: $role ?? 'guru', slug: 'akses-ditolak');

    if ($role === null) {
        ppdbMember($tenant, null);
    }

    get(school($tenant->slug, $path))->assertForbidden();
})->with(['guru', 'siswa', null])->with(['/ppdb', '/ppdb/pendaftar', '/ppdb/seleksi']);

it('sends guests to the login', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'akses-tamu']);

    get(school($tenant->slug, '/ppdb'))->assertRedirect();
});

it('shows the PPDB entry only to those who may view it', function (string $role, bool $sees) {
    $tenant = ppdbTenant(role: $role, slug: "menu-{$role}");

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->contains('PPDB') === $sees)
    );
})->with([
    'admin' => ['admin-sekolah', true],
    'staf' => ['staf-tu', true],
    'guru' => ['guru', false],
    'siswa' => ['siswa', false],
]);
