<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Qr\StaticQrCodes;
use Modules\Core\App\Domain\Models\Student;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * The static QR: a switch the school's admin turns on, a printable sheet of
 * every student's code, and scans that work only while the switch is on.
 * Friday 2 October 2026 at the school (Asia/Jakarta = UTC+7).
 */
beforeEach(function () {
    $this->travelTo('2026-10-01 23:50:00');
});

function staticCodeOf(Tenant $tenant, Student $student): string
{
    return attendanceSchool($tenant, fn () => app(StaticQrCodes::class)->payloadFor($student->id));
}

function switchStaticQr(Tenant $tenant, bool $on): void
{
    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'static_qr_enabled' => $on])
        ->assertSessionHasNoErrors();
}

it('has the static QR off until the admin switches it on, and keeps it when the field is left out', function () {
    $tenant = attendanceTenant(slug: 'qr-statis-saklar');

    get(school($tenant->slug, '/absensi/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('staticQrEnabled', false)
    );

    switchStaticQr($tenant, true);
    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:30'])->assertSessionHasNoErrors();

    get(school($tenant->slug, '/absensi/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('staticQrEnabled', true)
    );
});

it('prints every active student of the classes, only while the static QR is on', function () {
    $tenant = attendanceTenant(slug: 'qr-statis-cetak');
    $class = attendanceClass($tenant, 'X 1');
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceStudent($tenant, $class, 'Bimo', ['status' => 'inactive']);

    get(school($tenant->slug, '/absensi/qr-statis'))->assertForbidden();

    switchStaticQr($tenant, true);

    get(school($tenant->slug, '/absensi/qr-statis'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/StaticQr')
        ->has('groups', 1)
        ->where('groups.0.class', 'X 1')
        ->has('groups.0.students', 1)
        ->where('groups.0.students.0.name', 'Adit')
        ->where('groups.0.students.0.code', staticCodeOf($tenant, $adit))
    );

    switchStaticQr($tenant, false);

    get(school($tenant->slug, '/absensi/qr-statis'))->assertForbidden();
});

it('keeps the print page from anyone without the settings permission', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'qr-statis-guru');
    attendanceSchool($tenant, fn () => AttendanceSetting::current()->forceFill(['static_qr_enabled' => true])->save());

    get(school($tenant->slug, '/absensi/qr-statis'))->assertForbidden();
});

it('records a scan of the static QR only while the switch is on', function () {
    $tenant = attendanceTenant(slug: 'qr-statis-pindai');
    $adit = attendanceStudent($tenant, attendanceClass($tenant, 'X 1'), 'Adit');
    $code = staticCodeOf($tenant, $adit);
    $scan = fn () => postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'token' => $code]);

    $scan()->assertStatus(422)->assertJsonValidationErrors('scan');
    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(0);

    switchStaticQr($tenant, true);
    $scan()->assertOk()->assertJsonPath('student.name', 'Adit')->assertJsonPath('status', 'Hadir');

    // The code is not used up: the student can leave with the same paper.
    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-out', 'token' => $code])
        ->assertOk()->assertJsonPath('student.name', 'Adit');

    switchStaticQr($tenant, false);
    postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-out', 'token' => $code])->assertStatus(422);
});

it('refuses a forged static QR and a static QR of another school', function () {
    $other = attendanceTenant(slug: 'qr-statis-lain');
    $foreign = attendanceStudent($other, attendanceClass($other, 'X 1'), 'Asing');
    $foreignCode = staticCodeOf($other, $foreign);

    $tenant = attendanceTenant(slug: 'qr-statis-palsu');
    $adit = attendanceStudent($tenant, attendanceClass($tenant, 'X 1'), 'Adit');
    switchStaticQr($tenant, true);

    $genuine = staticCodeOf($tenant, $adit);
    $forged = 'simas-s1.'.$adit->id.'.'.str_repeat('0', 32);

    foreach ([$forged, $foreignCode, substr($genuine, 0, -1), 'simas-s1.abc.def'] as $code) {
        postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => 'gate-in', 'token' => $code])->assertStatus(422);
    }

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(0);
});

it('offers the print actions on the Siswa list only while the static QR is on', function () {
    $tenant = attendanceTenant(slug: 'qr-statis-daftar');

    get(school($tenant->slug, '/master/siswa'))->assertInertia(fn (Assert $page) => $page
        ->component('Core/Master/Students/Index')
        ->where('studentActions', [])
    );

    switchStaticQr($tenant, true);

    get(school($tenant->slug, '/master/siswa'))->assertInertia(fn (Assert $page) => $page
        ->has('studentActions', 1)
        ->where('studentActions.0.key', 'static-qr')
        ->where('studentActions.0.allUrl', route('attendance.static-qr'))
    );
});

it('prints the QR of one student, and refuses an inactive or unknown one', function () {
    $tenant = attendanceTenant(slug: 'qr-statis-satu');
    $class = attendanceClass($tenant, 'X 1');
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $bimo = attendanceStudent($tenant, $class, 'Bimo');
    $gone = attendanceStudent($tenant, $class, 'Citra', ['status' => 'left']);
    switchStaticQr($tenant, true);

    get(school($tenant->slug, '/absensi/qr-statis?siswa='.$adit->id))->assertInertia(fn (Assert $page) => $page
        ->has('groups', 1)
        ->has('groups.0.students', 1)
        ->where('groups.0.students.0.name', 'Adit')
        ->where('groups.0.students.0.code', staticCodeOf($tenant, $adit))
    );

    get(school($tenant->slug, '/absensi/qr-statis?siswa='.$gone->id))->assertNotFound();
    get(school($tenant->slug, '/absensi/qr-statis?siswa=999999'))->assertNotFound();
});
