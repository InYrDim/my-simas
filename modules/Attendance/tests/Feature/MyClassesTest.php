<?php

namespace Modules\Attendance\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Attendance\App\Domain\Models\LessonCheck;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\TeachingAssignment;

use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Kelas Saya › Kelas Aktif and Jadwal Hari Ini: the classes and lessons
 * of the signed-in teacher, the banner that follows the teaching range
 * and the todo checkbox.
 * Friday (weekday 5) 2 October 2026, 07:30 at the school (Asia/Jakarta).
 */
beforeEach(function () {
    $this->travelTo('2026-10-02 00:30:00');
});

it('shows the classes the teacher has lessons for', function () {
    $tenant = attendanceTenant(role: 'guru');
    $class = attendanceClass($tenant, 'X 1');
    attendanceTeach($tenant, $class, attendanceSlot($tenant), 'Matematika', auth()->id());

    // A teaching assignment without a lesson on the schedule is not
    // active yet.
    $other = attendanceClass($tenant, 'X 2');
    attendanceSchool($tenant, fn () => TeachingAssignment::factory()->create([
        'class_id' => $other->id,
        'subject_id' => Subject::factory()->create(['name' => 'Fisika'])->id,
    ]));

    get(school($tenant->slug, '/absensi/kelas-saya'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/MyClasses')
        ->has('classes', 1)
        ->where('classes.0.name', 'X 1')
        ->where('classes.0.subjects', ['Matematika'])
        ->where('classes.0.days', ['Jumat'])
        ->where('classes.0.lessons', 1));
});

it('shows no classes to a teacher without lessons', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'kelas-kosong');

    get(school($tenant->slug, '/absensi/kelas-saya'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/MyClasses')
        ->where('classes', []));
});

it('follows the teaching range with its banner', function (string $now, string $banner) {
    $tenant = attendanceTenant(role: 'guru');
    $class = attendanceClass($tenant);
    attendanceTeach($tenant, $class, attendanceSlot($tenant), 'Matematika', auth()->id());
    attendanceTeach($tenant, $class, attendanceSlot($tenant, ['start_time' => '08:00:00', 'end_time' => '08:45:00']), 'Fisika', auth()->id());

    $this->travelTo($now);

    get(school($tenant->slug, '/absensi/jadwal-hari-ini'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Today')
        ->where('banner', $banner)
        ->has('lessons', 2));
})->with([
    'before the first hour' => ['2026-10-01 23:50:00', 'upcoming'],
    'in the first hour' => ['2026-10-02 00:30:00', 'running'],
    'in the second hour' => ['2026-10-02 01:10:00', 'running'],
    'after the last hour' => ['2026-10-02 02:00:00', 'finished'],
]);

it('has no banner on a day without lessons', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'jadwal-kosong');

    get(school($tenant->slug, '/absensi/jadwal-hari-ini'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/Today')
        ->where('banner', 'none')
        ->where('lessons', []));
});

it('tells each lesson of today where it stands', function () {
    $tenant = attendanceTenant(role: 'guru');
    $class = attendanceClass($tenant);
    attendanceTeach($tenant, $class, attendanceSlot($tenant), 'Matematika', auth()->id());
    attendanceTeach($tenant, $class, attendanceSlot($tenant, ['start_time' => '08:00:00', 'end_time' => '08:45:00']), 'Fisika', auth()->id());

    // 08:10: the first lesson is over, the second is running.
    $this->travelTo('2026-10-02 01:10:00');

    get(school($tenant->slug, '/absensi/jadwal-hari-ini'))->assertInertia(fn (Assert $page) => $page
        ->where('lessons.0.state', 'finished')
        ->where('lessons.0.recorded', false)
        ->where('lessons.1.state', 'running'));
});

it('ticks a lesson off after its hour and takes it back', function () {
    $tenant = attendanceTenant(role: 'guru');
    [$class, $slot] = attendanceOwnLesson($tenant);

    // While the lesson runs it cannot be ticked off yet.
    put(school($tenant->slug, '/absensi/jadwal-hari-ini/centang'), ['period_slot_id' => $slot->id, 'checked' => true])
        ->assertSessionHasErrors('period_slot_id');

    expect(attendanceSchool($tenant, fn () => LessonCheck::query()->count()))->toBe(0);

    // After its hour.
    $this->travelTo('2026-10-02 02:00:00');

    put(school($tenant->slug, '/absensi/jadwal-hari-ini/centang'), ['period_slot_id' => $slot->id, 'checked' => true])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(attendanceSchool($tenant, fn () => LessonCheck::query()->sole()->class_id))->toBe($class->id);

    get(school($tenant->slug, '/absensi/jadwal-hari-ini'))->assertInertia(fn (Assert $page) => $page
        ->where('banner', 'finished')
        ->where('lessons.0.checked', true));

    put(school($tenant->slug, '/absensi/jadwal-hari-ini/centang'), ['period_slot_id' => $slot->id, 'checked' => false])
        ->assertSessionHasNoErrors();

    expect(attendanceSchool($tenant, fn () => LessonCheck::query()->count()))->toBe(0);
});

it('refuses a slot that is not on the own timetable', function () {
    $tenant = attendanceTenant(role: 'guru');
    attendanceOwnLesson($tenant);
    $foreign = attendanceSlot($tenant, ['start_time' => '08:00:00', 'end_time' => '08:45:00']);

    put(school($tenant->slug, '/absensi/jadwal-hari-ini/centang'), ['period_slot_id' => $foreign->id, 'checked' => false])
        ->assertSessionHasErrors('period_slot_id');
});

it('lists the active students of a class the teacher has lessons for', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'siswa-kelas');
    [$class] = attendanceOwnLesson($tenant);
    attendanceStudent($tenant, $class, 'Budi');
    attendanceStudent($tenant, $class, 'Sari', ['status' => 'left']);

    get(school($tenant->slug, "/absensi/kelas-saya/{$class->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Attendance/MyClassStudents')
        ->where('class.name', 'X 1')
        ->has('students', 1)
        ->where('students.0.name', 'Budi'));
});

it('does not show the students of a class outside the own timetable', function () {
    $tenant = attendanceTenant(role: 'guru', slug: 'kelas-orang');
    attendanceOwnLesson($tenant);
    $foreign = attendanceClass($tenant, 'X 2');
    attendanceStudent($tenant, $foreign, 'Rahasia');

    get(school($tenant->slug, "/absensi/kelas-saya/{$foreign->id}"))->assertNotFound();
});
