<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

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
