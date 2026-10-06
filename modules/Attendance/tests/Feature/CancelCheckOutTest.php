<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Core\App\Domain\Models\Student;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

require_once __DIR__.'/Support/helpers.php';

/*
 * Batalkan pulang: the office takes back a going-home record of today.
 * Friday 2 October 2026, 14:05 at the school (Asia/Jakarta = UTC+7).
 */
beforeEach(function () {
    $this->travelTo('2026-10-02 07:05:00');
});

/**
 * A student who came in at 06:50 and went home at 10:00 (early: a lesson
 * runs until 14:30).
 *
 * @return array{0: Tenant, 1: Student}
 */
function studentGoneHome(string $role = 'admin-sekolah', string $slug = 'pulang-batal'): array
{
    $tenant = attendanceTenant(role: $role, slug: $slug);
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->checkedIn('2026-10-01 23:50:00')->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
        'checked_out_at' => '2026-10-02 03:00:00', 'check_out_method' => 'manual', 'left_early' => true,
    ]));

    return [$tenant, $adit];
}

function cancelCheckOut(Tenant $tenant, int $studentId): TestResponse
{
    return delete(school($tenant->slug, '/absensi/input/pulang'), ['student_id' => $studentId]);
}

it('takes back the going-home record and keeps the arrival and the status', function () {
    [$tenant, $adit] = studentGoneHome();

    cancelCheckOut($tenant, $adit->id)->assertSessionHasNoErrors()->assertSessionHas('status', 'Catatan pulang dibatalkan.');

    $row = attendanceSchool($tenant, fn () => DailyAttendance::query()->first());

    expect($row->checked_out_at)->toBeNull()
        ->and($row->check_out_method)->toBeNull()
        ->and($row->left_early)->toBeFalse()
        ->and($row->checked_in_at)->not->toBeNull()
        ->and($row->status)->toBe(AttendanceStatus::Present)
        ->and($row->recorded_by)->toBe(auth()->id());
});

it('lets the student be scanned into a lesson and out again after the reset', function () {
    [$tenant, $adit] = studentGoneHome();
    $slot = attendanceSlot($tenant, ['start_time' => '13:00:00', 'end_time' => '14:30:00']);
    $scan = fn (string $mode) => postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => $mode, 'student_id' => $adit->id, 'class_id' => $adit->class_id, 'period_slot_id' => $slot->id,
    ]);

    $scan('lesson')->assertStatus(422);

    cancelCheckOut($tenant, $adit->id);

    $scan('lesson')->assertOk();
    $scan('gate-out')->assertOk()->assertJsonPath('status', 'Pulang awal');
});

it('refuses to take back a record that is not there', function () {
    [$tenant, $adit] = studentGoneHome();
    cancelCheckOut($tenant, $adit->id);

    cancelCheckOut($tenant, $adit->id)->assertSessionHasErrors('marks');
});

it('only touches today', function () {
    [$tenant, $adit] = studentGoneHome();
    $this->travelTo('2026-10-03 07:05:00');

    cancelCheckOut($tenant, $adit->id)->assertSessionHasErrors('marks');

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->first()->checked_out_at))->not->toBeNull();
});

it('does not reach a student of another school', function () {
    [, $foreign] = studentGoneHome(slug: 'pulang-lain');
    $tenant = attendanceTenant(slug: 'pulang-sendiri');

    cancelCheckOut($tenant, $foreign->id)->assertSessionHasErrors('marks');

    expect(attendanceSchool(Tenant::query()->where('slug', 'pulang-lain')->first(), fn () => DailyAttendance::query()->first()->checked_out_at))->not->toBeNull();
});

it('lets admin and staf-tu take it back and nobody else', function (?string $role, int $status) {
    [$tenant, $adit] = studentGoneHome();
    attendanceMember($tenant, $role);

    cancelCheckOut($tenant, $adit->id);

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->first()->checked_out_at === null))->toBe($status === 302);
})->with([
    'staf' => ['staf-tu', 302],
    'guru' => ['guru', 403],
    'siswa' => ['siswa', 403],
    'tanpa peran' => [null, 403],
]);

it('shows the going-home time on the daily input so it can be taken back', function () {
    [$tenant] = studentGoneHome();

    get(school($tenant->slug, '/absensi/input'))->assertInertia(fn (Assert $page) => $page
        ->where('date.isToday', true)
        ->where('students.0.checkedOut', '10:00')
    );
});
