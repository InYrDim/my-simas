<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Core\App\Domain\Models\Student;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Input Absensi and Rekap Hari Ini: the day's status of a class, entered
 * by hand. Friday 2 October 2026, 08:00 at the school (Asia/Jakarta).
 */
beforeEach(function () {
    $this->travelTo('2026-10-02 01:00:00');
});

/**
 * @param  list<array{student_id: int, status: string, note?: ?string}>  $marks
 */
function saveDaily(Tenant $tenant, int $classId, array $marks, string $date = '2026-10-02'): TestResponse
{
    return put(school($tenant->slug, '/absensi/input'), ['class_id' => $classId, 'date' => $date, 'marks' => $marks]);
}

function dailyRow(Tenant $tenant, Student $student, string $date = '2026-10-02'): ?DailyAttendance
{
    return attendanceSchool($tenant, fn () => DailyAttendance::query()->where('student_id', $student->id)->where('date', $date)->first());
}

it('saves the day of a class', function () {
    $tenant = attendanceTenant();
    $admin = auth()->id();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $bima = attendanceStudent($tenant, $class, 'Bima');

    saveDaily($tenant, $class->id, [
        ['student_id' => $adit->id, 'status' => 'present'],
        ['student_id' => $bima->id, 'status' => 'sick', 'note' => ' Demam '],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $row = dailyRow($tenant, $bima);

    expect($row->status)->toBe(AttendanceStatus::Sick)
        ->and($row->note)->toBe('Demam')
        ->and($row->class_id)->toBe($class->id)
        ->and($row->date)->toBe('2026-10-02')
        ->and($row->recorded_by)->toBe($admin)
        ->and($row->checked_in_at)->toBeNull()
        ->and(dailyRow($tenant, $adit)->status)->toBe(AttendanceStatus::Present);
});

it('updates the same rows when saved again', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    saveDaily($tenant, $class->id, [['student_id' => $adit->id, 'status' => 'absent']]);
    saveDaily($tenant, $class->id, [['student_id' => $adit->id, 'status' => 'permit', 'note' => 'Acara keluarga']]);

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(1)
        ->and(dailyRow($tenant, $adit)->status)->toBe(AttendanceStatus::Permit)
        ->and(dailyRow($tenant, $adit)->note)->toBe('Acara keluarga');
});

it('keeps the gate times when the day is saved again', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->checkedIn('2026-10-01 23:50:00')->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    saveDaily($tenant, $class->id, [['student_id' => $adit->id, 'status' => 'late']])->assertSessionHasNoErrors();

    $row = dailyRow($tenant, $adit);

    expect($row->status)->toBe(AttendanceStatus::Late)
        ->and($row->checked_in_at->toDateTimeString())->toBe('2026-10-01 23:50:00');
});

it('refuses a student of another class', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $other = attendanceClass($tenant, 'X 2');
    $outsider = attendanceStudent($tenant, $other, 'Citra');

    saveDaily($tenant, $class->id, [['student_id' => $outsider->id, 'status' => 'present']])
        ->assertSessionHasErrors('marks');

    expect(dailyRow($tenant, $outsider))->toBeNull();
});

it('refuses a day that has not come', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    saveDaily($tenant, $class->id, [['student_id' => $adit->id, 'status' => 'present']], '2026-10-03')
        ->assertSessionHasErrors('date');

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(0);
});

it('dates a record on the schools clock, not the servers', function () {
    // 2 October 23:30 UTC is already 3 October 06:30 in Jakarta.
    $this->travelTo('2026-10-02 23:30:00');

    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    saveDaily($tenant, $class->id, [['student_id' => $adit->id, 'status' => 'present']], '2026-10-03')
        ->assertSessionHasNoErrors();

    expect(dailyRow($tenant, $adit, '2026-10-03'))->not->toBeNull();
});

it('validates the form', function (array $payload, string $field) {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    $payload = array_replace_recursive(
        ['class_id' => $class->id, 'date' => '2026-10-02', 'marks' => [['student_id' => $adit->id, 'status' => 'present']]],
        $payload,
    );

    put(school($tenant->slug, '/absensi/input'), $payload)->assertSessionHasErrors($field);

    expect(attendanceSchool($tenant, fn () => DailyAttendance::query()->count()))->toBe(0);
})->with([
    'unknown status' => [['marks' => [['status' => 'bolos']]], 'marks.0.status'],
    'bad date' => [['date' => '02/10/2026'], 'date'],
    'unknown class' => [['class_id' => 999999], 'class_id'],
    'long note' => [['marks' => [['status' => 'sick', 'note' => str_repeat('a', 256)]]], 'marks.0.note'],
]);

it('shows the class with the recorded status and the gate times', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    attendanceClass($tenant, 'X 2');
    $adit = attendanceStudent($tenant, $class, 'Adit', ['nis' => '5001']);
    attendanceStudent($tenant, $class, 'Bima', ['nis' => '5002']);
    attendanceSchool($tenant, fn () => DailyAttendance::factory()->checkedIn('2026-10-01 23:50:00', AttendanceStatus::Late)->create([
        'student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02',
    ]));

    get(school($tenant->slug, "/absensi/input?kelas={$class->id}"))->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Input')
        ->where('date.iso', '2026-10-02')
        ->where('date.label', 'Jumat, 2 Oktober 2026')
        ->where('classId', (string) $class->id)
        ->where('classes', fn ($classes) => collect($classes)->pluck('label')->all() === ['X 1', 'X 2'])
        ->where('students.0.name', 'Adit')
        ->where('students.0.status', 'late')
        ->where('students.0.checkedIn', '06:50')
        ->where('students.0.checkedOut', null)
        ->where('students.1.name', 'Bima')
        ->where('students.1.status', null)
    );
});

it('falls back to today for a day that is not a real past day', function (string $query) {
    $tenant = attendanceTenant();
    attendanceClass($tenant);

    get(school($tenant->slug, "/absensi/input?tanggal={$query}"))->assertInertia(fn (Assert $page) => $page
        ->where('date.iso', '2026-10-02')
        ->where('date.isToday', true)
    );
})->with(['2026-10-05', '2026-02-30', 'kemarin', '']);

it('counts the day per class on Rekap Hari Ini', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $empty = attendanceClass($tenant, 'X 2');
    $students = collect(['Adit', 'Bima', 'Citra', 'Dewi', 'Eka'])
        ->map(fn (string $name) => attendanceStudent($tenant, $class, $name));
    attendanceStudent($tenant, $empty, 'Fajar');

    saveDaily($tenant, $class->id, [
        ['student_id' => $students[0]->id, 'status' => 'present'],
        ['student_id' => $students[1]->id, 'status' => 'late'],
        ['student_id' => $students[2]->id, 'status' => 'sick'],
        ['student_id' => $students[3]->id, 'status' => 'absent'],
    ]);

    get(school($tenant->slug, '/absensi'))->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Overview')
        ->where('date.iso', '2026-10-02')
        ->where('totals', ['present' => 1, 'late' => 1, 'sick' => 1, 'permit' => 0, 'absent' => 1, 'pending' => 2, 'total' => 6])
        ->where('classes.0.name', 'X 1')
        ->where('classes.0.present', 1)
        ->where('classes.0.pending', 1)
        ->where('classes.0.total', 5)
        ->where('classes.0.submitted', false)
        ->where('classes.1.name', 'X 2')
        ->where('classes.1.pending', 1)
        ->where('can.record', true)
    );

    saveDaily($tenant, $class->id, [['student_id' => $students[4]->id, 'status' => 'permit']]);

    get(school($tenant->slug, '/absensi'))->assertInertia(fn (Assert $page) => $page
        ->where('classes.0.submitted', true)
        ->where('classes.0.permit', 1)
    );

    // Another day has its own count.
    get(school($tenant->slug, '/absensi?tanggal=2026-10-01'))->assertInertia(fn (Assert $page) => $page
        ->where('date.isToday', false)
        ->where('totals.pending', 6)
        ->where('totals.present', 0)
    );
});

it('lets every staff role save and refuses the others', function (?string $role, bool $allowed) {
    $tenant = attendanceTenant(slug: 'harian-izin');
    $class = attendanceClass($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceMember($tenant, $role);

    $response = saveDaily($tenant, $class->id, [['student_id' => $adit->id, 'status' => 'sick']]);

    $allowed ? $response->assertRedirect()->assertSessionHasNoErrors() : $response->assertForbidden();

    expect(dailyRow($tenant, $adit) !== null)->toBe($allowed);
})->with([
    'staf' => ['staf-tu', true],
    'siswa' => ['siswa', false],
    'tanpa peran' => [null, false],
]);

it('keeps two schools apart', function () {
    $other = attendanceTenant(slug: 'harian-lain');
    $otherClass = attendanceClass($other);
    $foreign = attendanceStudent($other, $otherClass, 'Asing');
    attendanceSchool($other, fn () => DailyAttendance::factory()->status(AttendanceStatus::Absent)->create([
        'student_id' => $foreign->id, 'class_id' => $otherClass->id, 'date' => '2026-10-02',
    ]));

    $tenant = attendanceTenant(slug: 'harian-sendiri');
    $class = attendanceClass($tenant);
    attendanceStudent($tenant, $class, 'Adit');

    // Another school's class and student are simply not there.
    saveDaily($tenant, $otherClass->id, [['student_id' => $foreign->id, 'status' => 'present']])
        ->assertSessionHasErrors('class_id');
    saveDaily($tenant, $class->id, [['student_id' => $foreign->id, 'status' => 'present']])
        ->assertSessionHasErrors('marks');

    get(school($tenant->slug, '/absensi'))->assertInertia(fn (Assert $page) => $page
        ->where('totals.absent', 0)
        ->where('totals.total', 1)
    );

    expect(dailyRow($other, $foreign)->status)->toBe(AttendanceStatus::Absent);
});
