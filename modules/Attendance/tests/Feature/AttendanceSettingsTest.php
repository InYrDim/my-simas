<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Pengaturan Absensi: the school's cut-off for arriving on time.
 */

it('starts at 07:00 and keeps what the admin sets', function () {
    $tenant = attendanceTenant();

    get(school($tenant->slug, '/absensi/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Settings')
        ->where('lateAfter', '07:00')
        ->where('can.manageNotices', true)
    );

    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:15'])
        ->assertRedirect()->assertSessionHasNoErrors();
    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '06:45'])->assertSessionHasNoErrors();

    expect(attendanceSchool($tenant, fn () => AttendanceSetting::query()->count()))->toBe(1)
        ->and(attendanceSchool($tenant, fn () => AttendanceSetting::current()->late_after))->toBe('06:45:00');

    get(school($tenant->slug, '/absensi/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('lateAfter', '06:45')
    );
});

it('refuses a time that is not a time', function (mixed $value) {
    $tenant = attendanceTenant();

    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => $value])->assertSessionHasErrors('late_after');

    expect(attendanceSchool($tenant, fn () => AttendanceSetting::current()->late_after))->toBe('07:00:00');
})->with(['', 'pagi', '25:00', '7.30']);

it('is for the school admin only', function (string $role) {
    $tenant = attendanceTenant(role: $role, slug: "atur-tulis-{$role}");

    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '08:00'])->assertForbidden();

    expect(attendanceSchool($tenant, fn () => AttendanceSetting::current()->late_after))->toBe('07:00:00');
})->with(['guru', 'staf-tu', 'siswa']);

it('keeps the setting of each school', function () {
    $other = attendanceTenant(slug: 'atur-lain');
    put(school($other->slug, '/absensi/pengaturan'), ['late_after' => '08:00']);

    $tenant = attendanceTenant(slug: 'atur-sendiri');

    get(school($tenant->slug, '/absensi/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('lateAfter', '07:00')
    );

    expect(attendanceSchool($other, fn () => AttendanceSetting::current()->late_after))->toBe('08:00:00');
});
