<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Who may open which Absensi page: the pages follow the attendance.*
 * permissions of the default roles, and the whole module follows the
 * school's module flag.
 */

/**
 * The sidebar labels under "Absensi" for the signed-in user.
 *
 * @return list<string>
 */
function attendanceMenu(string $slug): array
{
    $labels = [];

    get(school($slug, '/beranda'))->assertInertia(function (Assert $page) use (&$labels): void {
        $entry = collect($page->toArray()['props']['tenantNav'])->firstWhere('label', 'Absensi');
        $labels = collect($entry['children'] ?? [])->pluck('label')->all();
    });

    return $labels;
}

it('registers the attendance permissions', function () {
    expect(app(PermissionRegistry::class)->forModule('attendance'))->toBe([
        'attendance.view',
        'attendance.daily.record',
        'attendance.lesson.record',
        'attendance.settings.manage',
        'attendance.qr.show',
        'attendance.mine.view',
        'attendance.class.record',
    ]);
});

it('opens the recap pages for every staff role', function (string $role, string $path) {
    $tenant = attendanceTenant(role: $role, slug: "akses-{$role}");

    get(school($tenant->slug, $path))->assertOk();
})->with(['admin-sekolah', 'staf-tu'])->with(['/absensi', '/absensi/rekap', '/absensi/input']);

it('gives a teacher the input page but not the school-wide recaps', function (string $path, int $status) {
    $tenant = attendanceTenant(role: 'guru', slug: 'akses-guru');

    get(school($tenant->slug, $path))->assertStatus($status);
})->with([
    ['/absensi/input', 200],
    ['/absensi', 403],
    ['/absensi/rekap', 403],
]);

it('keeps the settings page for the school admin', function (string $role, int $status) {
    $tenant = attendanceTenant(role: $role, slug: "atur-{$role}");

    get(school($tenant->slug, '/absensi/pengaturan'))->assertStatus($status);
})->with([
    ['admin-sekolah', 200],
    ['guru', 403],
    ['staf-tu', 403],
]);

it('keeps lesson attendance for teachers and the admin', function (string $role, int $status) {
    $tenant = attendanceTenant(role: $role, slug: "jam-{$role}");

    get(school($tenant->slug, '/absensi/jam-pelajaran'))->assertStatus($status);
})->with([
    ['admin-sekolah', 200],
    ['guru', 200],
    ['staf-tu', 403],
]);

it('refuses a student on every staff page', function (string $path) {
    $tenant = attendanceTenant(role: 'siswa', slug: 'akses-siswa');

    get(school($tenant->slug, $path))->assertForbidden();
})->with(['/absensi', '/absensi/input', '/absensi/rekap', '/absensi/jam-pelajaran', '/absensi/pindai', '/absensi/pengaturan']);

it('refuses a user without a role', function () {
    $tenant = attendanceTenant(slug: 'akses-tanpa-peran');
    attendanceMember($tenant, null);

    get(school($tenant->slug, '/absensi'))->assertForbidden();
});

it('sends guests to the login', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'akses-tamu']);

    get(school($tenant->slug, '/absensi'))->assertRedirect();
});

it('shows each role the menu entries it may use', function (string $role, array $labels) {
    $tenant = attendanceTenant(role: $role, slug: "menu-{$role}");

    expect(attendanceMenu($tenant->slug))->toBe($labels);
})->with([
    'admin' => ['admin-sekolah', ['Rekap Hari Ini', 'Input Absensi', 'Jam Pelajaran', 'Pindai QR', 'Rekap Bulanan', 'Pengaturan']],
    'guru' => ['guru', []],
    'staf' => ['staf-tu', ['Rekap Hari Ini', 'Input Absensi', 'Pindai QR', 'Rekap Bulanan']],
]);

it('gives a teacher an Absensi Saya menu with the own-class pages only', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'menu-saya-guru');

    get(school($tenant->slug, '/beranda'))->assertInertia(function (Assert $page): void {
        $nav = collect($page->toArray()['props']['tenantNav']);
        $mine = $nav->firstWhere('label', 'Absensi Saya');

        expect($nav->pluck('label')->contains('Absensi'))->toBeFalse()
            ->and($mine['group'])->toBe('Saya')
            ->and(collect($mine['children'])->pluck('label')->all())->toBe(['Input Absensi', 'Jam Pelajaran', 'Pindai QR']);
    });
});

it('shows a student only the QR entry', function () {
    $tenant = attendanceTenant(role: 'siswa', slug: 'menu-siswa');

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->all() === ['Beranda', 'Profil Saya', 'QR Absensi', 'Absensi Saya'])
    );
});

it('hides and blocks Absensi for a tenant without the module', function () {
    $tenant = attendanceTenant(enabled: false);

    get(school($tenant->slug, '/absensi'))->assertForbidden();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => ! collect($nav)->pluck('label')->contains('Absensi'))
    );
});
