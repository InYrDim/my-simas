<?php

namespace Modules\Attendance\Tests\Feature;

use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Absensi Saya: a student reads only their own daily history.
 */

it('shows a student their own month and nobody else\'s', function () {
    $tenant = attendanceTenant(role: 'siswa', slug: 'saya-siswa');
    $user = auth()->user();
    $class = attendanceClass($tenant);
    $me = attendanceStudent($tenant, $class, 'Aditya', ['user_id' => $user->id]);
    $other = attendanceStudent($tenant, $class, 'Siswa Lain');
    $month = now($tenant->timezone)->format('Y-m');

    attendanceSchool($tenant, function () use ($me, $other, $class, $month): void {
        DailyAttendance::factory()->status(AttendanceStatus::Present)->create(['student_id' => $me->id, 'class_id' => $class->id, 'date' => "{$month}-01"]);
        DailyAttendance::factory()->status(AttendanceStatus::Sick, 'demam')->create(['student_id' => $me->id, 'class_id' => $class->id, 'date' => "{$month}-02"]);
        DailyAttendance::factory()->status(AttendanceStatus::Absent)->create(['student_id' => $other->id, 'class_id' => $class->id, 'date' => "{$month}-01"]);
    });

    get(school($tenant->slug, '/absensi/saya'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Attendance/MyAttendance')
        ->where('student.name', 'Aditya')
        ->has('days', 2)
        ->where('days.0.statusLabel', 'Sakit')
        ->where('days.0.note', 'demam')
        ->where('totals', fn ($totals) => collect($totals)->pluck('count', 'status')->all() === ['present' => 1, 'late' => 0, 'sick' => 1, 'permit' => 0, 'absent' => 0])
        ->where('month.next', null));
});

it('falls back to this month for a bad or future month', function (string $bulan) {
    $tenant = attendanceTenant(role: 'siswa', slug: 'saya-bulan');
    attendanceStudent($tenant, attendanceClass($tenant), 'Aditya', ['user_id' => auth()->id()]);

    get(school($tenant->slug, "/absensi/saya?bulan={$bulan}"))->assertOk()->assertInertia(fn ($page) => $page
        ->where('month.iso', now($tenant->timezone)->format('Y-m')));
})->with(['ngawur', '2999-01']);

it('refuses an account that is not linked to a student', function () {
    $tenant = attendanceTenant(role: 'siswa', slug: 'saya-tanpa-siswa');

    get(school($tenant->slug, '/absensi/saya'))->assertForbidden();
});

it('refuses staff roles', function (string $role) {
    $tenant = attendanceTenant(role: $role, slug: "saya-{$role}");

    get(school($tenant->slug, '/absensi/saya'))->assertForbidden();
})->with(['admin-sekolah', 'guru', 'staf-tu']);
