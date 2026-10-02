<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Every Absensi page renders for a signed-in admin of a school that has
 * the module enabled. (The module flag itself: AttendanceAccessTest.)
 */
dataset('attendancePages', [
    'overview' => ['/absensi', 'Attendance/Overview'],
    'input' => ['/absensi/input', 'Attendance/Input'],
    'lessons' => ['/absensi/jam-pelajaran', 'Attendance/Lessons'],
    'scan' => ['/absensi/pindai', 'Attendance/Scan'],
    'monthly' => ['/absensi/rekap', 'Attendance/Monthly'],
    'settings' => ['/absensi/pengaturan', 'Attendance/Settings'],
]);

it('renders each absensi page for a school user', function (string $path, string $component) {
    $tenant = attendanceTenant();

    get(school($tenant->slug, $path))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component($component)
    );
})->with('attendancePages');

it('lists the Absensi group in the sidebar when the module is enabled', function () {
    $tenant = attendanceTenant();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => collect($nav)->pluck('label')->contains('Absensi'))
    );
});
