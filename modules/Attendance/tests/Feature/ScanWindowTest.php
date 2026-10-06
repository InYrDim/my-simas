<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Pindai pelajaran hanya di dalam jamnya. Friday 2 October 2026; the
 * helper slot runs 07:15–08:00 at the school (Asia/Jakarta, UTC+7), so
 * 07:10 is 00:10 UTC.
 */

/**
 * Scan a student into the lesson by hand pick, at a UTC time of the day.
 *
 * @return array{0: Tenant, 1: TestResponse}
 */
function scanLessonAt(string $utc, string $role = 'admin-sekolah', bool $ownLesson = false): array
{
    test()->travelTo("2026-10-02 {$utc}");

    $tenant = attendanceTenant(role: $role);
    $class = attendanceClass($tenant);
    $slot = attendanceSlot($tenant);

    if ($ownLesson) {
        attendanceTeach($tenant, $class, $slot, 'Matematika', auth()->id());
    }

    $student = attendanceStudent($tenant, $class, 'Adit');

    return [$tenant, postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $student->id, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ])];
}

function lessonScanCount(Tenant $tenant): int
{
    return attendanceSchool($tenant, fn (): int => LessonAttendance::query()->count());
}

it('accepts a lesson scan inside the lesson hour', function () {
    [$tenant, $response] = scanLessonAt('00:30:00');

    $response->assertOk();
    expect(lessonScanCount($tenant))->toBe(1);
});

it('accepts a lesson scan inside the early tolerance', function () {
    [, $response] = scanLessonAt('00:11:00');

    $response->assertOk();
});

it('refuses a lesson scan before the tolerance opens', function () {
    [$tenant, $response] = scanLessonAt('00:05:00');

    $response->assertStatus(422)->assertJsonValidationErrors('scan');
    expect($response->json('errors.scan.0'))->toContain('belum dibuka')
        ->and(lessonScanCount($tenant))->toBe(0);
});

it('refuses a lesson scan after the lesson is over, however late', function (string $utc) {
    [$tenant, $response] = scanLessonAt($utc);

    $response->assertStatus(422)->assertJsonValidationErrors('scan');
    expect($response->json('errors.scan.0'))->toContain('sudah selesai')->toContain('Riwayat Absensi')
        ->and(lessonScanCount($tenant))->toBe(0);
})->with(['right at the end' => '01:00:00', 'ten at night' => '15:00:00']);

it('follows the tolerance the school set', function () {
    test()->travelTo('2026-10-02 00:00:00');
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $slot = attendanceSlot($tenant);
    $student = attendanceStudent($tenant, $class, 'Adit');
    attendanceSchool($tenant, fn () => AttendanceSetting::current()->update(['lesson_scan_early_minutes' => 30]));

    // 07:00, a quarter hour before the lesson: inside 30 minutes.
    postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $student->id, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ])->assertOk();
});

it('lets a teacher scan the own running lesson', function () {
    [, $response] = scanLessonAt('00:30:00', 'guru', ownLesson: true);

    $response->assertOk();
});

it('refuses a teacher a lesson that is not on the own timetable', function () {
    test()->travelTo('2026-10-02 00:30:00');
    $tenant = attendanceTenant(role: 'guru');
    $class = attendanceClass($tenant);
    // The teacher teaches the class in a later slot; the running slot is another teacher's.
    attendanceTeach($tenant, $class, attendanceSlot($tenant, ['start_time' => '09:00:00', 'end_time' => '09:45:00']), 'Matematika', auth()->id());
    $running = attendanceSlot($tenant);
    $student = attendanceStudent($tenant, $class, 'Adit');

    $response = postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $student->id, 'class_id' => $class->id, 'period_slot_id' => $running->id,
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.scan.0'))->toBe('Jam ini bukan jadwal mengajar Anda.')
        ->and(lessonScanCount($tenant))->toBe(0);
});

it('has nothing for a teacher on the scanner outside the own lesson hour', function () {
    test()->travelTo('2026-10-02 06:00:00');
    $tenant = attendanceTenant(role: 'guru');
    $class = attendanceClass($tenant);
    attendanceTeach($tenant, $class, attendanceSlot($tenant), 'Matematika', auth()->id());

    get(school($tenant->slug, '/absensi/pindai'))->assertInertia(fn (Assert $page) => $page
        ->where('ownLessonOnly', true)
        ->where('classes', [])
        ->where('slots', [])
        ->where('slotId', '')
    );
});

it('keeps the free choice of class for the school-wide permission', function () {
    test()->travelTo('2026-10-02 00:30:00');
    $tenant = attendanceTenant();
    attendanceClass($tenant, 'X 1');
    attendanceClass($tenant, 'X 2');
    attendanceSlot($tenant);

    get(school($tenant->slug, '/absensi/pindai'))->assertInertia(fn (Assert $page) => $page
        ->where('ownLessonOnly', false)
        ->has('classes', 2)
    );
});

it('does not carry the tolerance of one school into another', function () {
    test()->travelTo('2026-10-02 00:05:00');
    $first = attendanceTenant(slug: 'sekolah-a');
    attendanceSchool($first, fn () => AttendanceSetting::current()->update(['lesson_scan_early_minutes' => 30]));
    $second = attendanceTenant(slug: 'sekolah-b');
    $class = attendanceClass($second);
    $slot = attendanceSlot($second);
    $student = attendanceStudent($second, $class, 'Bima');

    // 07:05 is ten minutes before the lesson: refused by the default of 5.
    postJson(school($second->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $student->id, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ])->assertStatus(422);
});

/**
 * A scan at the gate by hand pick.
 */
function gateScanAt(Tenant $tenant, string $mode, int $studentId): TestResponse
{
    return postJson(school($tenant->slug, '/absensi/pindai'), ['mode' => $mode, 'student_id' => $studentId]);
}

it('takes gate arrivals only between opening and closing time', function (string $utc, int $status) {
    test()->travelTo($utc);
    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');

    $response = gateScanAt($tenant, 'gate-in', $adit->id)->assertStatus($status);

    if ($status === 422) {
        expect($response->json('errors.scan.0'))->toBe('Gerbang menerima pemindaian pukul 05.00–18.00. Koreksi lewat Input harian.');
    }
})->with([
    'a minute before it opens' => ['2026-10-01 21:59:00', 422],
    'the minute it opens' => ['2026-10-01 22:00:00', 200],
    'the minute it closes' => ['2026-10-02 11:00:00', 200],
    'a minute after it closes' => ['2026-10-02 11:01:00', 422],
    'ten at night' => ['2026-10-02 15:00:00', 422],
]);

it('refuses going home outside the gate hours', function () {
    test()->travelTo('2026-10-02 15:00:00');
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->checkedIn('2026-10-02 00:00:00')->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    gateScanAt($tenant, 'gate-out', $adit->id)->assertStatus(422);

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->first()->checked_out_at))->toBeNull();
});

it('marks leaving before the last lesson is over as left early', function (string $utc, string $status, bool $leftEarly) {
    test()->travelTo($utc);
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    attendanceSlot($tenant);
    attendanceSlot($tenant, ['start_time' => '13:00:00', 'end_time' => '14:30:00']);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->checkedIn('2026-10-01 23:50:00')->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    gateScanAt($tenant, 'gate-out', $adit->id)->assertOk()->assertJsonPath('status', $status);

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->first()->left_early))->toBe($leftEarly);
})->with([
    'after the first lesson' => ['2026-10-02 01:30:00', 'Pulang awal', true],
    'a minute before the last lesson ends' => ['2026-10-02 07:29:00', 'Pulang awal', true],
    'when the last lesson ends' => ['2026-10-02 07:30:00', 'Pulang', false],
    'in the evening' => ['2026-10-02 10:00:00', 'Pulang', false],
]);

it('does not call it early to leave on a day without lessons', function () {
    test()->travelTo('2026-10-02 01:30:00');
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->checkedIn('2026-10-01 23:50:00')->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    gateScanAt($tenant, 'gate-out', $adit->id)->assertOk()->assertJsonPath('status', 'Pulang');
});

it('follows the gate hours the school set', function () {
    test()->travelTo('2026-10-02 12:00:00');
    $tenant = attendanceTenant();
    $adit = attendanceStudent($tenant, attendanceClass($tenant), 'Adit');

    // 19:00 at the school: closed by default, open once the school moves the closing time.
    gateScanAt($tenant, 'gate-in', $adit->id)->assertStatus(422);

    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'gate_opens_at' => '06:00', 'gate_closes_at' => '20:00'])
        ->assertSessionHasNoErrors();

    gateScanAt($tenant, 'gate-in', $adit->id)->assertOk();
});

it('refuses gate hours that close before they open', function () {
    $tenant = attendanceTenant();

    put(school($tenant->slug, '/absensi/pengaturan'), ['late_after' => '07:00', 'gate_opens_at' => '18:00', 'gate_closes_at' => '05:00'])
        ->assertSessionHasErrors('gate_closes_at');

    expect(attendanceSchool($tenant, fn () => AttendanceSetting::current()->gateOpensAt()))->toBe('05:00');
});

it('refuses a lesson scan for a student already recorded as gone home', function () {
    test()->travelTo('2026-10-02 00:30:00');
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $slot = attendanceSlot($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $bima = attendanceStudent($tenant, $class, 'Bima');
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->checkedIn('2026-10-01 23:50:00')->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02', 'checked_out_at' => '2026-10-02 00:20:00',
    ]));

    $scan = fn (int $studentId) => postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $studentId, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ]);

    $scan($adit->id)->assertStatus(422)->assertJsonPath('errors.scan.0', 'Adit sudah tercatat pulang pukul 07.20.');
    // A student without any gate record is still scanned in, as before.
    $scan($bima->id)->assertOk();

    expect(lessonScanCount($tenant))->toBe(1);
});
