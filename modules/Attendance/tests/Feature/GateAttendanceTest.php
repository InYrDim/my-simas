<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Qr\QrTokens;
use Modules\Core\App\Domain\Models\Student;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * The school gate: in and out, by a student's one-time QR or by hand.
 * Friday 2 October 2026 at the school (Asia/Jakarta = UTC+7).
 */
beforeEach(function () {
    // 06:50 at the school.
    $this->travelTo('2026-10-01 23:50:00');
});

/**
 * @param  array<string, mixed>  $payload
 */
function scan(Tenant $tenant, array $payload): TestResponse
{
    return postJson(school($tenant->slug, '/absensi/pindai'), $payload);
}

function gateRow(Tenant $tenant, Student $student): ?DailyAttendance
{
    return attendanceSchool($tenant, fn () => DailyAttendance::query()->where('student_id', $student->id)->first());
}

/**
 * A code as the student's page would show it.
 */
function qrOf(Tenant $tenant, Student $student): string
{
    return attendanceSchool($tenant, fn () => app(QrTokens::class)->issue($student->id))['token'];
}

/**
 * A signed-in student account linked to the student record.
 */
function studentAccount(Tenant $tenant, Student $student): User
{
    $user = attendanceMember($tenant, 'siswa');
    attendanceSchool($tenant, fn () => $student->forceFill(['user_id' => $user->id])->save());

    return $user;
}

it('records a student coming in on time from the QR', function () {
    $tenant = attendanceTenant();
    $admin = auth()->id();
    $class = attendanceClass($tenant, 'X 1');
    $adit = attendanceStudent($tenant, $class, 'Adit', ['nis' => '5001']);

    scan($tenant, ['mode' => 'gate-in', 'token' => qrOf($tenant, $adit)])
        ->assertOk()
        ->assertExactJson([
            'student' => ['id' => $adit->id, 'name' => 'Adit', 'nis' => '5001', 'class' => 'X 1'],
            'time' => '06:50',
            'status' => 'Hadir',
        ]);

    $row = gateRow($tenant, $adit);

    expect($row->status)->toBe(AttendanceStatus::Present)
        ->and($row->date)->toBe('2026-10-02')
        ->and($row->class_id)->toBe($class->id)
        ->and($row->checked_in_at->toDateTimeString())->toBe('2026-10-01 23:50:00')
        ->and($row->check_in_method)->toBe(RecordMethod::Qr)
        ->and($row->recorded_by)->toBe($admin);
});

it('marks a student late after the schools cut-off', function (string $utc, string $status) {
    $this->travelTo($utc);

    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk()->assertJsonPath('status', $status);
})->with([
    'the cut-off minute itself' => ['2026-10-02 00:00:30', 'Hadir'],
    'a minute later' => ['2026-10-02 00:01:00', 'Terlambat'],
]);

it('follows the cut-off the school set', function () {
    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');
    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '06:30']);

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $adit->id])->assertJsonPath('status', 'Terlambat');

    expect(gateRow($tenant, $adit)->status)->toBe(AttendanceStatus::Late);
});

it('records by hand for a student without a phone', function () {
    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();

    expect(gateRow($tenant, $adit)->check_in_method)->toBe(RecordMethod::Manual);
});

it('lets arriving overrule an absence marked earlier that day', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->status(AttendanceStatus::Absent, 'Tanpa kabar')->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(1)
        ->and(gateRow($tenant, $adit)->status)->toBe(AttendanceStatus::Present)
        ->and(gateRow($tenant, $adit)->checked_in_at)->not->toBeNull();
});

it('refuses a second arrival and says when the first was', function () {
    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();
    $this->travel(10)->minutes();

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $adit->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.scan.0', 'Adit sudah tercatat masuk pukul 06.50.');

    expect(gateRow($tenant, $adit)->checked_in_at->toDateTimeString())->toBe('2026-10-01 23:50:00');
});

it('uses a QR code only once', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $code = qrOf($tenant, $adit);

    scan($tenant, ['mode' => 'gate-in', 'token' => $code])->assertOk();

    // The same picture, shown again for going home.
    scan($tenant, ['mode' => 'gate-out', 'token' => $code])
        ->assertStatus(422)
        ->assertJsonPath('errors.scan.0', 'Kode QR tidak dikenal atau sudah kedaluwarsa. Minta siswa menampilkan kode baru.');

    expect(gateRow($tenant, $adit)->checked_out_at)->toBeNull();
});

it('refuses an expired or unknown code', function () {
    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');
    $code = qrOf($tenant, $adit);
    $this->travel(61)->seconds();

    scan($tenant, ['mode' => 'gate-in', 'token' => $code])->assertStatus(422);
    scan($tenant, ['mode' => 'gate-in', 'token' => 'bukan-kode'])->assertStatus(422);

    expect(gateRow($tenant, $adit))->toBeNull();
});

it('records going home after coming in', function () {
    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $adit->id])->assertOk();

    // 14:05 at the school.
    $this->travelTo('2026-10-02 07:05:00');

    scan($tenant, ['mode' => 'gate-out', 'token' => qrOf($tenant, $adit)])
        ->assertOk()
        ->assertJsonPath('time', '14:05')
        ->assertJsonPath('status', 'Pulang');

    $row = gateRow($tenant, $adit);

    expect($row->checked_out_at->toDateTimeString())->toBe('2026-10-02 07:05:00')
        ->and($row->check_out_method)->toBe(RecordMethod::Qr)
        ->and($row->status)->toBe(AttendanceStatus::Present);

    scan($tenant, ['mode' => 'gate-out', 'student_id' => $adit->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.scan.0', 'Adit sudah tercatat pulang pukul 14.05.');
});

it('refuses going home for a student who never came in', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $sick = attendanceStudent($tenant, $class, 'Bima');
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->status(AttendanceStatus::Sick)->create([
        'student_id' => $sick->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    scan($tenant, ['mode' => 'gate-out', 'student_id' => $adit->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.scan.0', 'Adit belum tercatat masuk hari ini.');
    scan($tenant, ['mode' => 'gate-out', 'student_id' => $sick->id])->assertStatus(422);

    expect(gateRow($tenant, $adit))->toBeNull()
        ->and(gateRow($tenant, $sick)->checked_out_at)->toBeNull();
});

it('refuses a student who left the school or belongs to another', function () {
    $other = attendanceTenant(slug: 'gerbang-lain');
    $foreign = attendanceStudent($other, attendanceClass($other), 'Asing');

    $tenant = attendanceTenant(slug: 'gerbang-sendiri');
    $gone = attendanceSchool($tenant, fn () => Student::factory()->status('left')->create(['name' => 'Keluar']));

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $gone->id])->assertStatus(422);
    scan($tenant, ['mode' => 'gate-in', 'student_id' => $foreign->id])->assertStatus(422);
    // A code from the other school's page means nothing here.
    scan($tenant, ['mode' => 'gate-in', 'token' => qrOf($other, $foreign)])->assertStatus(422);

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(0)
        ->and(attendanceSchool($other, fn () => DailyAttendance::query()->count()))->toBe(0);
});

it('validates a scan', function (array $payload, string $field) {
    $tenant = attendanceTenant();

    scan($tenant, $payload)->assertStatus(422)->assertJsonValidationErrors($field);
})->with([
    'no mode' => [['student_id' => 1], 'mode'],
    'unknown mode' => [['mode' => 'kantin', 'student_id' => 1], 'mode'],
    'nobody' => [['mode' => 'gate-in'], 'token'],
    'lesson without class' => [['mode' => 'lesson', 'student_id' => 1], 'class_id'],
]);

it('lets staff record at the gate and nobody else', function (?string $role, int $status) {
    $tenant = attendanceTenant(slug: 'gerbang-izin');
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');
    attendanceMember($tenant, $role);

    scan($tenant, ['mode' => 'gate-in', 'student_id' => $adit->id])->assertStatus($status);

    expect(gateRow($tenant, $adit) !== null)->toBe($status === 200);
})->with([
    'guru' => ['guru', 403],
    'staf' => ['staf-tu', 200],
    'siswa' => ['siswa', 403],
    'tanpa peran' => [null, 403],
]);

it('finds students by name or NIS for the manual pick', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $adit = attendanceStudent($tenant, $class, 'Aditya Pratama', ['nis' => '5001']);
    attendanceStudent($tenant, $class, 'Bima Sakti', ['nis' => '6002']);

    get(school($tenant->slug, '/absensi/pindai/siswa?q=adit'))
        ->assertOk()
        ->assertExactJson(['students' => [['id' => $adit->id, 'name' => 'Aditya Pratama', 'nis' => '5001', 'class' => 'X 1']]]);

    get(school($tenant->slug, '/absensi/pindai/siswa?q=6002'))->assertJsonPath('students.0.name', 'Bima Sakti');
    get(school($tenant->slug, '/absensi/pindai/siswa'))->assertExactJson(['students' => []]);

    attendanceMember($tenant, 'siswa');
    get(school($tenant->slug, '/absensi/pindai/siswa?q=adit'))->assertForbidden();
});

it('offers the scanner modes the user may use', function (string $role, bool $gate, bool $lesson) {
    $tenant = attendanceTenant(role: $role, slug: "pindai-{$role}");

    get(school($tenant->slug, '/absensi/pindai'))->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Scan')
        ->where('can', ['gate' => $gate, 'lesson' => $lesson])
        ->where('date.iso', '2026-10-02')
    );
})->with([
    ['admin-sekolah', true, true],
    ['guru', false, true],
    ['staf-tu', true, false],
]);

it('shows a student the QR page with what the gate recorded today', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $adit = attendanceStudent($tenant, $class, 'Adit', ['nis' => '5001']);
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->checkedIn('2026-10-01 23:40:00')->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));
    studentAccount($tenant, $adit);

    get(school($tenant->slug, '/absensi/qr-saya'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/MyQr')
        ->where('student', ['name' => 'Adit', 'nis' => '5001', 'class' => 'X 1'])
        ->where('today.label', 'Jumat, 2 Oktober 2026')
        ->where('today.status', 'Hadir')
        ->where('today.checkedIn', '06:40')
        ->where('today.checkedOut', null)
        ->where('refreshEvery', 45)
    );
});

it('hands a student a code that records that student', function () {
    $tenant = attendanceTenant();
    $admin = auth()->user();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit', ['nis' => '77001234']);
    studentAccount($tenant, $adit);

    $code = postJson(school($tenant->slug, '/absensi/qr-saya/token'))
        ->assertOk()
        ->assertJsonPath('expiresIn', 60)
        ->json('token');

    // The code carries nothing about the student.
    expect($code)->not->toContain('77001234')->not->toContain('Adit');

    actingAs($admin);

    scan($tenant, ['mode' => 'gate-in', 'token' => $code])->assertOk()->assertJsonPath('student.name', 'Adit');
});

it('keeps the QR page for accounts of active students', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);

    // Staff hold no QR permission.
    get(school($tenant->slug, '/absensi/qr-saya'))->assertForbidden();
    postJson(school($tenant->slug, '/absensi/qr-saya/token'))->assertForbidden();

    // A student account that no student record carries.
    attendanceMember($tenant, 'siswa');
    get(school($tenant->slug, '/absensi/qr-saya'))->assertForbidden();
    postJson(school($tenant->slug, '/absensi/qr-saya/token'))->assertForbidden();

    // A student who left.
    $gone = attendanceStudent($tenant, $class, 'Keluar', ['status' => 'left']);
    studentAccount($tenant, $gone);
    get(school($tenant->slug, '/absensi/qr-saya'))->assertForbidden();
    postJson(school($tenant->slug, '/absensi/qr-saya/token'))->assertForbidden();
});

it('limits how often a student may ask for a code', function () {
    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');
    studentAccount($tenant, $adit);

    foreach (range(1, 20) as $attempt) {
        postJson(school($tenant->slug, '/absensi/qr-saya/token'))->assertOk();
    }

    postJson(school($tenant->slug, '/absensi/qr-saya/token'))->assertStatus(429);

    $this->travel(61)->seconds();

    postJson(school($tenant->slug, '/absensi/qr-saya/token'))->assertOk();
});
