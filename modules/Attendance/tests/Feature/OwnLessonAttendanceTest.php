<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\LessonAttendance;
use Modules\Attendance\App\Domain\Models\LessonCheck;
use Modules\Attendance\App\Domain\Models\LessonSession;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Kelas Saya › Absensi Kelas and Riwayat Absensi: the signed-in
 * teacher's own lessons, open for filling only in their own hour and
 * corrected at any time from the history.
 * Friday (weekday 5) 2 October 2026, 07:30 at the school (Asia/Jakarta).
 */
beforeEach(function () {
    $this->travelTo('2026-10-02 00:30:00');
});

it('opens the lesson running now and saves its roll', function () {
    $tenant = attendanceTenant(role: 'guru');
    [$class, $slot, $subject] = attendanceOwnLesson($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    get(school($tenant->slug, '/absensi/absen-kelas'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/ClassRoll')
        ->where('editable', true)
        ->where('slotId', (string) $slot->id)
        ->where('selected.subjectName', 'Matematika')
        ->where('recorded', false)
        ->where('students.0.name', 'Adit')
        ->where('students.0.status', null));

    put(school($tenant->slug, '/absensi/absen-kelas'), [
        'date' => '2026-10-02',
        'period_slot_id' => $slot->id,
        'marks' => [['student_id' => $adit->id, 'status' => 'absent']],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $session = attendanceSchool($tenant, fn () => LessonSession::query()->sole());

    expect($session->class_id)->toBe($class->id)
        ->and($session->subject_id)->toBe($subject->id)
        ->and($session->recorded_by)->toBe(auth()->id())
        ->and(attendanceSchool($tenant, fn () => LessonAttendance::query()->sole()->status->value))->toBe('absent')
        ->and(attendanceSchool($tenant, fn () => LessonCheck::query()->sole()->class_id))->toBe($class->id);

    // The save ticks the lesson off today's todo.
    get(school($tenant->slug, '/absensi/jadwal-hari-ini'))->assertInertia(fn (Assert $page) => $page
        ->where('lessons.0.checked', true)
        ->where('lessons.0.recorded', true));
});

it('refuses to save outside the lesson hour', function () {
    $tenant = attendanceTenant(role: 'guru');
    [$class, $slot] = attendanceOwnLesson($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    $payload = [
        'date' => '2026-10-02',
        'period_slot_id' => $slot->id,
        'marks' => [['student_id' => $adit->id, 'status' => 'present']],
    ];

    // 06:50, before the hour: the page opens it read-only.
    $this->travelTo('2026-10-01 23:50:00');

    get(school($tenant->slug, '/absensi/absen-kelas'))->assertInertia(fn (Assert $page) => $page
        ->where('editable', false));

    put(school($tenant->slug, '/absensi/absen-kelas'), $payload)->assertSessionHasErrors('period_slot_id');

    // 09:00, after the hour.
    $this->travelTo('2026-10-02 02:00:00');

    put(school($tenant->slug, '/absensi/absen-kelas'), $payload)->assertSessionHasErrors('period_slot_id');

    expect(attendanceSchool($tenant, fn () => LessonSession::query()->count()))->toBe(0);
});

it('refuses a slot that is not on the own timetable', function () {
    $tenant = attendanceTenant(role: 'guru');
    [$class] = attendanceOwnLesson($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $foreign = attendanceSlot($tenant, ['start_time' => '08:00:00', 'end_time' => '08:45:00']);

    put(school($tenant->slug, '/absensi/absen-kelas'), [
        'date' => '2026-10-02',
        'period_slot_id' => $foreign->id,
        'marks' => [['student_id' => $adit->id, 'status' => 'present']],
    ])->assertSessionHasErrors('period_slot_id');

    expect(attendanceSchool($tenant, fn () => LessonSession::query()->count()))->toBe(0);
});

it('corrects a past lesson from the history', function () {
    $tenant = attendanceTenant(role: 'guru');
    [$class, $slot] = attendanceOwnLesson($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    // 09:00 of the following Friday; last Friday (25 September) is over.
    $this->travelTo('2026-10-02 02:00:00');

    get(school($tenant->slug, '/absensi/riwayat?tanggal=2026-09-25'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/History')
        ->where('lessons.0.className', 'X 1')
        ->where('lessons.0.summary', null)
        ->where('selected', null));

    put(school($tenant->slug, '/absensi/riwayat'), [
        'date' => '2026-09-25',
        'period_slot_id' => $slot->id,
        'marks' => [['student_id' => $adit->id, 'status' => 'absent']],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(attendanceSchool($tenant, fn () => LessonSession::query()->sole()->date))->toBe('2026-09-25');
});

it('shows the saved recap and reopens its roll from the history', function () {
    $tenant = attendanceTenant(role: 'guru');
    [$class, $slot] = attendanceOwnLesson($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');
    $bima = attendanceStudent($tenant, $class, 'Bima');

    put(school($tenant->slug, '/absensi/absen-kelas'), [
        'date' => '2026-10-02',
        'period_slot_id' => $slot->id,
        'marks' => [
            ['student_id' => $adit->id, 'status' => 'present'],
            ['student_id' => $bima->id, 'status' => 'absent'],
        ],
    ])->assertSessionHasNoErrors();

    get(school($tenant->slug, '/absensi/riwayat'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/History')
        ->where('lessons.0.summary.present', 1)
        ->where('lessons.0.summary.sick', 0)
        ->where('lessons.0.summary.permit', 0)
        ->where('lessons.0.summary.absent', 1));

    get(school($tenant->slug, "/absensi/riwayat?jam={$slot->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('selected.slotId', $slot->id)
        ->where('recorded', true)
        ->where('students.0.status', 'present')
        ->where('students.1.status', 'absent'));
});

it('keeps another school out', function () {
    $other = attendanceTenant(slug: 'kelas-lain');
    $otherClass = attendanceClass($other, 'IX 9');
    $otherSlot = attendanceSlot($other);
    attendanceTeach($other, $otherClass, $otherSlot, 'Biologi');

    $tenant = attendanceTenant(role: 'guru', slug: 'kelas-sendiri');
    [$class] = attendanceOwnLesson($tenant);
    $adit = attendanceStudent($tenant, $class, 'Adit');

    // The other school's slot is not on this teacher's timetable.
    put(school($tenant->slug, '/absensi/riwayat'), [
        'date' => '2026-10-02',
        'period_slot_id' => $otherSlot->id,
        'marks' => [['student_id' => $adit->id, 'status' => 'present']],
    ])->assertSessionHasErrors('period_slot_id');

    expect(attendanceSchool($tenant, fn () => LessonSession::query()->count()))->toBe(0)
        ->and(attendanceSchool($other, fn () => LessonSession::query()->count()))->toBe(0);
});
