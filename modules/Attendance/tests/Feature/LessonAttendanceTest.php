<?php

namespace Modules\Attendance\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;
use Modules\Attendance\App\Domain\Enums\RecordMethod;
use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonSession;
use Modules\Attendance\App\Domain\Qr\QrTokens;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Platform\App\Domain\Models\Tenant;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Absensi Jam Pelajaran: one class in one lesson slot of one day.
 * Friday (weekday 5) 2 October 2026, 07:30 at the school (Asia/Jakarta).
 */
beforeEach(function () {
    $this->travelTo('2026-10-02 00:30:00');
});

/**
 * A slot of the bell schedule; Friday's first lesson by default.
 *
 * @param  array<string, mixed>  $attributes
 */
function lessonSlot(Tenant $tenant, array $attributes = []): PeriodSlot
{
    return attendanceSchool($tenant, fn (): PeriodSlot => PeriodSlot::factory()->create(['day' => 5, ...$attributes]));
}

/**
 * A subject taught in the class by a new teacher; `$userId` links the
 * teacher to a login account.
 */
function lessonSubject(Tenant $tenant, ClassGroup $class, string $name, ?int $userId = null): Subject
{
    return attendanceSchool($tenant, function () use ($class, $name, $userId): Subject {
        $subject = Subject::factory()->create(['name' => $name]);
        $teacher = Teacher::factory()->create(['name' => "Guru {$name}"]);
        $teacher->forceFill(['user_id' => $userId])->save();
        TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);

        return $subject;
    });
}

/**
 * @param  array<string, mixed>  $payload
 */
function saveLesson(Tenant $tenant, array $payload): TestResponse
{
    return put(school($tenant->slug, '/absensi/jam-pelajaran'), ['date' => '2026-10-02', 'subject_id' => null, ...$payload]);
}

/**
 * @return array<int, string> status per student id
 */
function lessonMarks(Tenant $tenant): array
{
    return attendanceSchool($tenant, fn () => LessonAttendance::query()->get()
        ->mapWithKeys(fn (LessonAttendance $row): array => [$row->student_id => $row->status->value])->all());
}

it('saves a lesson of a class', function () {
    $tenant = attendanceTenant();
    $admin = auth()->id();
    $class = attendanceClass($tenant);
    $slot = lessonSlot($tenant);
    $subject = lessonSubject($tenant, $class, 'Matematika');
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $bima = attendanceStudent($tenant, $class, 'Bima');

    saveLesson($tenant, [
        'class_id' => $class->id,
        'period_slot_id' => $slot->id,
        'subject_id' => $subject->id,
        'marks' => [
            ['student_id' => $adit->id, 'status' => 'present'],
            ['student_id' => $bima->id, 'status' => 'absent'],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $session = attendanceSchool($tenant, fn () => LessonSession::query()->sole());

    expect($session->class_id)->toBe($class->id)
        ->and($session->date)->toBe('2026-10-02')
        ->and($session->period_slot_id)->toBe($slot->id)
        ->and($session->start_time)->toBe('07:15:00')
        ->and($session->end_time)->toBe('08:00:00')
        ->and($session->subject_id)->toBe($subject->id)
        ->and($session->teacher_id)->not->toBeNull()
        ->and($session->recorded_by)->toBe($admin)
        ->and(lessonMarks($tenant))->toBe([$adit->id => 'present', $bima->id => 'absent']);
});

it('keeps one session per class, day and slot', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $slot = lessonSlot($tenant);
    $subject = lessonSubject($tenant, $class, 'Matematika');
    $adit = attendanceStudent($tenant, $class, 'Adit');

    $payload = ['class_id' => $class->id, 'period_slot_id' => $slot->id];

    saveLesson($tenant, [...$payload, 'marks' => [['student_id' => $adit->id, 'status' => 'absent']]]);
    saveLesson($tenant, [...$payload, 'subject_id' => $subject->id, 'marks' => [['student_id' => $adit->id, 'status' => 'sick']]])
        ->assertSessionHasNoErrors();

    expect(attendanceSchool($tenant, fn () => LessonSession::query()->count()))->toBe(1)
        ->and(attendanceSchool($tenant, fn () => LessonSession::query()->sole()->subject_id))->toBe($subject->id)
        ->and(lessonMarks($tenant))->toBe([$adit->id => 'sick']);
});

it('refuses what is not a lesson of that class and day', function (string $case, string $field) {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $slot = lessonSlot($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $other = attendanceClass($tenant, 'X 2');

    $payload = ['class_id' => $class->id, 'period_slot_id' => $slot->id, 'marks' => [['student_id' => $adit->id, 'status' => 'present']]];

    $payload = match ($case) {
        'a break' => [...$payload, 'period_slot_id' => lessonSlot($tenant, ['start_time' => '09:30:00', 'end_time' => '09:45:00', 'type' => 'Istirahat'])->id],
        'a slot of another weekday' => [...$payload, 'period_slot_id' => lessonSlot($tenant, ['day' => 1])->id],
        'an unknown slot' => [...$payload, 'period_slot_id' => 999999],
        'a class of a past year' => [...$payload, 'class_id' => attendanceSchool($tenant, fn () => ClassGroup::factory()->create([
            'academic_year_id' => AcademicYear::factory()->archived()->create()->id,
            'grade_id' => $class->grade_id,
        ]))->id],
        'a subject not taught there' => [...$payload, 'subject_id' => lessonSubject($tenant, $other, 'Fisika')->id],
        'a student of another class' => [...$payload, 'marks' => [['student_id' => attendanceStudent($tenant, $other, 'Citra')->id, 'status' => 'present']]],
        'a day that has not come' => [...$payload, 'date' => '2026-10-09'],
        'being late' => [...$payload, 'marks' => [['student_id' => $adit->id, 'status' => 'late']]],
    };

    saveLesson($tenant, $payload)->assertSessionHasErrors($field);

    expect(attendanceSchool($tenant, fn () => LessonSession::query()->count()))->toBe(0)
        ->and(lessonMarks($tenant))->toBe([]);
})->with([
    ['a break', 'period_slot_id'],
    ['a slot of another weekday', 'period_slot_id'],
    ['an unknown slot', 'period_slot_id'],
    ['a class of a past year', 'class_id'],
    ['a subject not taught there', 'subject_id'],
    ['a student of another class', 'marks'],
    ['a day that has not come', 'date'],
    ['being late', 'marks.0.status'],
]);

it('opens the lesson with the days absences already filled in', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    lessonSlot($tenant, ['start_time' => '07:15:00', 'end_time' => '08:00:00']);
    $second = lessonSlot($tenant, ['start_time' => '08:00:00', 'end_time' => '08:45:00']);
    lessonSlot($tenant, ['start_time' => '08:45:00', 'end_time' => '09:00:00', 'type' => 'Istirahat']);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $bima = attendanceStudent($tenant, $class, 'Bima');
    attendanceStudent($tenant, $class, 'Citra');

    attendanceSchool($tenant, function () use ($class, $adit, $bima): void {
        DailyAttendance::factory()->status(AttendanceStatus::Sick)->create(['student_id' => $adit->id, 'class_id' => $class->id, 'date' => '2026-10-02']);
        DailyAttendance::factory()->status(AttendanceStatus::Late)->create(['student_id' => $bima->id, 'class_id' => $class->id, 'date' => '2026-10-02']);
    });

    get(school($tenant->slug, '/absensi/jam-pelajaran'))->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Lessons')
        ->where('date.iso', '2026-10-02')
        ->where('slots', fn ($slots) => collect($slots)->pluck('label')->all() === ['Jam ke-1 · 07:15–08:00', 'Jam ke-2 · 08:00–08:45'])
        // 07:30: the first lesson is the one running.
        ->where('slots.0.value', fn ($value) => $value !== (string) $second->id)
        ->where('slotId', fn ($value) => $value !== (string) $second->id && $value !== '')
        ->where('recorded', false)
        ->where('students.0.name', 'Adit')
        ->where('students.0.status', 'sick')
        ->where('students.0.daily', 'sick')
        ->where('students.1.status', null)
        ->where('students.1.daily', 'late')
        ->where('students.2.status', null)
        ->where('students.2.daily', null)
    );

    get(school($tenant->slug, "/absensi/jam-pelajaran?jam={$second->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('slotId', (string) $second->id)
    );
});

it('shows a saved lesson as it was saved', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant);
    $slot = lessonSlot($tenant);
    $subject = lessonSubject($tenant, $class, 'Matematika');
    $adit = attendanceStudent($tenant, $class, 'Adit');

    saveLesson($tenant, [
        'class_id' => $class->id, 'period_slot_id' => $slot->id, 'subject_id' => $subject->id,
        'marks' => [['student_id' => $adit->id, 'status' => 'permit']],
    ]);

    get(school($tenant->slug, '/absensi/jam-pelajaran'))->assertInertia(fn (Assert $page) => $page
        ->where('recorded', true)
        ->where('subjectId', (string) $subject->id)
        ->where('subjects.0.label', 'Matematika — Guru Matematika')
        ->where('students.0.status', 'permit')
    );
});

it('has no lessons on a day without lesson slots', function () {
    $tenant = attendanceTenant();
    attendanceClass($tenant);
    lessonSlot($tenant, ['day' => 1]);

    get(school($tenant->slug, '/absensi/jam-pelajaran'))->assertInertia(fn (Assert $page) => $page
        ->where('slots', [])
        ->where('slotId', '')
    );
});

it('offers a teacher only the own lesson that is running on the scanner', function () {
    $tenant = attendanceTenant(role: 'guru');
    $teacherAccount = auth()->id();
    attendanceClass($tenant, 'X 1');
    $taught = attendanceClass($tenant, 'X 2');
    attendanceClass($tenant, 'X 3');
    $slot = lessonSlot($tenant);
    lessonSubject($tenant, $taught, 'Bahasa Indonesia');
    attendanceTeach($tenant, $taught, $slot, 'Matematika', $teacherAccount);

    get(school($tenant->slug, '/absensi/pindai'))->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Scan')
        ->where('ownLessonOnly', true)
        ->where('classes', [
            ['value' => (string) $taught->id, 'label' => 'X 2', 'mine' => true],
        ])
        ->where('classId', (string) $taught->id)
        ->where('slotId', (string) $slot->id)
    );
});

it('refuses a teacher a class that is not the own', function () {
    $tenant = attendanceTenant(role: 'guru');
    $teacherAccount = auth()->id();
    $taught = attendanceClass($tenant, 'X 1');
    $other = attendanceClass($tenant, 'X 2');
    $slot = lessonSlot($tenant);
    lessonSubject($tenant, $taught, 'Matematika', $teacherAccount);
    $citra = attendanceStudent($tenant, $other, 'Citra');

    // A scanned student of another class is refused.
    postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $citra->id, 'class_id' => $other->id, 'period_slot_id' => $slot->id,
    ])->assertStatus(422)->assertJsonValidationErrors('class_id');

    // The school-wide pages are the office's now.
    get(school($tenant->slug, "/absensi/jam-pelajaran?kelas={$other->id}"))->assertForbidden();
    saveLesson($tenant, [
        'class_id' => $other->id, 'period_slot_id' => $slot->id,
        'marks' => [['student_id' => $citra->id, 'status' => 'present']],
    ])->assertForbidden();

    expect(lessonMarks($tenant))->toBe([]);
});

it('lets a teacher record the own lesson by scan', function () {
    $tenant = attendanceTenant(role: 'guru');
    $teacherAccount = auth()->id();
    $taught = attendanceClass($tenant, 'X 1');
    $slot = lessonSlot($tenant);
    attendanceTeach($tenant, $taught, $slot, 'Matematika', $teacherAccount);
    $adit = attendanceStudent($tenant, $taught, 'Adit');

    postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $adit->id, 'class_id' => $taught->id, 'period_slot_id' => $slot->id,
    ])->assertOk();

    expect(lessonMarks($tenant))->toBe([$adit->id => 'present']);
});

it('has no own classes for an account that is not a teachers', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    lessonSubject($tenant, $class, 'Matematika', 999999);

    get(school($tenant->slug, '/absensi/jam-pelajaran'))->assertInertia(fn (Assert $page) => $page
        ->where('classes.0.mine', false)
        ->where('subjectId', '')
    );
});

it('marks a student present in a lesson by scan and opens the session', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $slot = lessonSlot($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $bima = attendanceStudent($tenant, $class, 'Bima');
    $code = attendanceSchool($tenant, fn () => app(QrTokens::class)->issue($adit->id))['token'];

    postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'token' => $code, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ])->assertOk()->assertJsonPath('student.name', 'Adit')->assertJsonPath('status', 'Hadir')->assertJsonPath('time', '07:30');

    postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $bima->id, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ])->assertOk();

    $rows = attendanceSchool($tenant, fn () => LessonAttendance::query()->orderBy('student_id')->get());

    expect(attendanceSchool($tenant, fn () => LessonSession::query()->count()))->toBe(1)
        ->and($rows[0]->method)->toBe(RecordMethod::Qr)
        ->and($rows[0]->scanned_at->toDateTimeString())->toBe('2026-10-02 00:30:00')
        ->and($rows[1]->method)->toBe(RecordMethod::Manual);

    // A second scan of the same student is noticed.
    postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $adit->id, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ])->assertStatus(422)->assertJsonPath('errors.scan.0', 'Adit sudah tercatat hadir di jam ini.');

    // Saving the list afterwards keeps the scan a scan.
    saveLesson($tenant, [
        'class_id' => $class->id, 'period_slot_id' => $slot->id,
        'marks' => [['student_id' => $adit->id, 'status' => 'present'], ['student_id' => $bima->id, 'status' => 'absent']],
    ]);

    $rows = attendanceSchool($tenant, fn () => LessonAttendance::query()->orderBy('student_id')->get());

    expect($rows[0]->method)->toBe(RecordMethod::Qr)
        ->and($rows[0]->scanned_at)->not->toBeNull()
        ->and($rows[1]->status)->toBe(AttendanceStatus::Absent);
});

it('refuses a lesson scan of a student of another class', function () {
    $tenant = attendanceTenant();
    $class = attendanceClass($tenant, 'X 1');
    $other = attendanceClass($tenant, 'X 2');
    $slot = lessonSlot($tenant);
    $citra = attendanceStudent($tenant, $other, 'Citra');

    postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $citra->id, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ])->assertStatus(422)->assertJsonPath('errors.scan.0', 'Citra bukan siswa kelas X 1.');

    expect(lessonMarks($tenant))->toBe([]);
});

it('keeps lesson attendance for the admin and refuses the others', function (?string $role, bool $allowed) {
    $tenant = attendanceTenant(slug: 'jam-izin');
    $class = attendanceClass($tenant);
    $slot = lessonSlot($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    attendanceMember($tenant, $role);

    $save = saveLesson($tenant, ['class_id' => $class->id, 'period_slot_id' => $slot->id, 'marks' => [['student_id' => $adit->id, 'status' => 'sick']]]);
    $allowed ? $save->assertRedirect()->assertSessionHasNoErrors() : $save->assertForbidden();

    $scan = postJson(school($tenant->slug, '/absensi/pindai'), [
        'mode' => 'lesson', 'student_id' => $adit->id, 'class_id' => $class->id, 'period_slot_id' => $slot->id,
    ]);
    $allowed ? $scan->assertOk() : $scan->assertForbidden();
})->with([
    'admin' => ['admin-sekolah', true],
    'staf' => ['staf-tu', false],
    'siswa' => ['siswa', false],
]);

it('keeps two schools apart', function () {
    $other = attendanceTenant(slug: 'jam-lain');
    $otherClass = attendanceClass($other);
    $otherSlot = lessonSlot($other);
    $foreign = attendanceStudent($other, $otherClass, 'Asing');

    $tenant = attendanceTenant(slug: 'jam-sendiri');
    $class = attendanceClass($tenant);
    $slot = lessonSlot($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    saveLesson($tenant, ['class_id' => $otherClass->id, 'period_slot_id' => $slot->id, 'marks' => [['student_id' => $adit->id, 'status' => 'present']]])
        ->assertSessionHasErrors('class_id');
    saveLesson($tenant, ['class_id' => $class->id, 'period_slot_id' => $otherSlot->id, 'marks' => [['student_id' => $adit->id, 'status' => 'present']]])
        ->assertSessionHasErrors('period_slot_id');
    saveLesson($tenant, ['class_id' => $class->id, 'period_slot_id' => $slot->id, 'marks' => [['student_id' => $foreign->id, 'status' => 'present']]])
        ->assertSessionHasErrors('marks');

    expect(attendanceSchool($tenant, fn () => LessonSession::query()->count()))->toBe(0)
        ->and(attendanceSchool($other, fn () => LessonSession::query()->count()))->toBe(0);
});
