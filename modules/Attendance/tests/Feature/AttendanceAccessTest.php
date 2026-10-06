<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
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
        'attendance.class.view-own',
    ]);
});

it('opens the recap pages for every staff role', function (string $role, string $path) {
    $tenant = attendanceTenant(role: $role, slug: "akses-{$role}");

    get(school($tenant->slug, $path))->assertOk();
})->with(['admin-sekolah', 'staf-tu'])->with(['/absensi', '/absensi/rekap', '/absensi/input']);

it('keeps the school-wide attendance pages from a teacher', function (string $path) {
    $tenant = attendanceTenant(role: 'guru', slug: 'akses-guru');

    get(school($tenant->slug, $path))->assertForbidden();
})->with(['/absensi', '/absensi/rekap', '/absensi/input', '/absensi/jam-pelajaran']);

it('keeps the settings page for the school admin', function (string $role, int $status) {
    $tenant = attendanceTenant(role: $role, slug: "atur-{$role}");

    get(school($tenant->slug, '/absensi/pengaturan'))->assertStatus($status);
})->with([
    ['admin-sekolah', 200],
    ['guru', 403],
    ['staf-tu', 403],
]);

it('keeps the school-wide lesson page for the admin', function (string $role, int $status) {
    $tenant = attendanceTenant(role: $role, slug: "jam-{$role}");

    get(school($tenant->slug, '/absensi/jam-pelajaran'))->assertStatus($status);
})->with([
    ['admin-sekolah', 200],
    ['guru', 403],
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

it('gives a teacher Jadwal Saya and one Kelas Mengajar menu', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'menu-saya-guru');

    get(school($tenant->slug, '/beranda'))->assertInertia(function (Assert $page): void {
        $nav = collect($page->toArray()['props']['tenantNav']);
        $mine = $nav->firstWhere('label', 'Kelas Mengajar');
        $schedule = $nav->firstWhere('label', 'Jadwal Saya');

        expect($nav->pluck('label')->all())->toBe(['Beranda', 'Profil Saya', 'Jadwal Saya', 'Kelas Mengajar'])
            ->and($mine['group'])->toBe('Saya')
            ->and($mine['href'])->toBe('/absensi/kelas-saya')
            ->and(collect($mine['children'])->pluck('label')->all())->toBe(['Kelas Aktif', 'Absensi Kelas', 'Pindai QR'])
            ->and(collect($mine['children'])->pluck('href')->all())->toBe(['/absensi/kelas-saya', '/absensi/absen-kelas', '/absensi/pindai'])
            ->and($schedule['group'])->toBe('Saya')
            ->and($schedule['href'])->toBe('/absensi/jadwal-saya');
    });
});

it('opens the own pages for a teacher', function (string $path) {
    $tenant = attendanceTenant(role: 'guru', slug: 'kelas-guru');

    get(school($tenant->slug, $path))->assertOk();
})->with(['/absensi/kelas-saya', '/absensi/jadwal-saya', '/absensi/absen-kelas', '/absensi/riwayat']);

it('no longer has the empty Absensi Saya page for a teacher', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'kelas-tanpa-segera');

    get(school($tenant->slug, '/absensi/segera-hadir'))->assertNotFound();
});

it('keeps the teacher pages from every other role', function (string $role, string $path) {
    $tenant = attendanceTenant(role: $role, slug: "kelas-bukan-{$role}");

    get(school($tenant->slug, $path))->assertForbidden();
})->with([
    ['admin-sekolah', '/absensi/kelas-saya'],
    ['admin-sekolah', '/absensi/absen-kelas'],
    ['staf-tu', '/absensi/kelas-saya'],
    ['staf-tu', '/absensi/absen-kelas'],
    ['siswa', '/absensi/kelas-saya'],
    ['siswa', '/absensi/absen-kelas'],
]);

it('closes the lesson pages while the school has lesson attendance off', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'kelas-switch');
    attendanceOwnLesson($tenant);

    attendanceSchool($tenant, fn () => AttendanceSetting::current()->update(['lesson_enabled' => false]));

    get(school($tenant->slug, '/absensi/absen-kelas'))->assertForbidden();
    get(school($tenant->slug, '/absensi/riwayat'))->assertForbidden();
    get(school($tenant->slug, '/absensi/kelas-saya'))->assertOk();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect(collect($nav)->firstWhere('label', 'Kelas Mengajar')['children'])->pluck('label')->all() === ['Kelas Aktif'])
    );
});

it('shows a student the QR entry and Kelas Saya with Absensi Saya inside', function () {
    $tenant = attendanceTenant(role: 'siswa', slug: 'menu-siswa');

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->all() === ['Beranda', 'Profil Saya', 'QR Absensi', 'Kelas Saya'])
        ->where('tenantNav.3.children', fn ($children) => collect($children)->pluck('label')->all() === ['Info Kelas', 'Jadwal Pelajaran', 'Mata Pelajaran & Guru', 'Absensi Saya'])
    );
});

it('hides and blocks Absensi for a tenant without the module', function () {
    $tenant = attendanceTenant(enabled: false);

    get(school($tenant->slug, '/absensi'))->assertForbidden();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => ! collect($nav)->pluck('label')->contains('Absensi'))
    );
});
