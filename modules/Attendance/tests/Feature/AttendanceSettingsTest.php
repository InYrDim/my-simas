<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
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

it('has the gate and the lessons on until the admin switches them off', function () {
    $tenant = attendanceTenant();

    get(school($tenant->slug, '/absensi/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('gateEnabled', true)
        ->where('lessonEnabled', true)
    );

    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'gate_enabled' => false, 'lesson_enabled' => true])
        ->assertSessionHasNoErrors();

    get(school($tenant->slug, '/absensi/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('gateEnabled', false)
        ->where('lessonEnabled', true)
    );

    // Leaving the switches out keeps them as they are.
    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:30'])->assertSessionHasNoErrors();

    expect(attendanceSchool($tenant, fn () => AttendanceSetting::gateEnabled()))->toBeFalse();
});

it('closes the lesson pages and the lesson scan when lessons are off', function () {
    $tenant = attendanceTenant(slug: 'saklar-jam');
    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'lesson_enabled' => false]);

    get(school($tenant->slug, '/absensi/jam-pelajaran'))->assertForbidden();
    get(school($tenant->slug, '/absensi/pindai'))->assertInertia(fn (Assert $page) => $page
        ->where('can.gate', true)
        ->where('can.lesson', false)
    );
    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'lesson', 'student_id' => 1, 'class_id' => 1, 'period_slot_id' => 1])
        ->assertForbidden();
});

it('closes the gate scan when the gate is off but keeps the scanner for lessons', function () {
    $tenant = attendanceTenant(slug: 'saklar-gerbang');
    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'gate_enabled' => false]);

    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'student_id' => 1])->assertForbidden();
    get(school($tenant->slug, '/absensi/pindai'))->assertInertia(fn (Assert $page) => $page
        ->where('can.gate', false)
        ->where('can.lesson', true)
    );

    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'lesson_enabled' => false]);

    get(school($tenant->slug, '/absensi/pindai'))->assertForbidden();
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

it('has copying the previous lesson on until the admin switches it off', function () {
    $tenant = attendanceTenant(slug: 'salin-jam');

    get(school($tenant->slug, '/absensi/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('lessonCopyPreviousEnabled', true)
    );

    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'lesson_copy_previous_enabled' => false])
        ->assertSessionHasNoErrors();

    expect(attendanceSchool($tenant, fn () => AttendanceSetting::copyPreviousEnabled()))->toBeFalse();

    // Leaving it out keeps it as it is.
    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:30'])->assertSessionHasNoErrors();

    expect(attendanceSchool($tenant, fn () => AttendanceSetting::copyPreviousEnabled()))->toBeFalse();
});
