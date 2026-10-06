<?php

namespace Modules\Attendance\Tests\Feature;

use Modules\Core\App\Domain\Models\TimetableEntry;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Kelas Saya (student): the class, timetable and subjects of the student
 * behind the signed-in account, and nobody else's.
 */

/**
 * A student account in X 1 with a classmate and one scheduled lesson.
 *
 * @return array{0: Tenant, 1: ClassGroup}
 */
function studentWithClass(string $slug): array
{
    $tenant = attendanceTenant(role: 'siswa', slug: $slug);
    $class = attendanceClass($tenant, 'X 1');
    attendanceStudent($tenant, $class, 'Aditya', ['user_id' => auth()->id()]);
    attendanceStudent($tenant, $class, 'Budi');
    attendanceTeach($tenant, $class, attendanceSlot($tenant), 'Matematika');

    return [$tenant, $class];
}

it('shows the class, its homeroom and the classmates', function () {
    [$tenant] = studentWithClass('kelasku-info');

    get(school($tenant->slug, '/absensi/kelasku'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Attendance/MyClassInfo')
        ->where('class.name', 'X 1')
        ->where('class.studentCount', 2)
        ->where('classmates', ['Aditya', 'Budi']));
});

it('shows the timetable of the class with each teacher', function () {
    [$tenant] = studentWithClass('kelasku-jadwal');

    get(school($tenant->slug, '/absensi/kelasku/jadwal'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Attendance/MyClassTimetable')
        ->where('className', 'X 1')
        ->has('days', 1)
        ->where('days.0.day', 'Jumat')
        ->where('days.0.lessons.0.subject', 'Matematika')
        ->where('days.0.lessons.0.teacher', 'Guru Matematika')
        ->where('days.0.lessons.0.start', '07:15'));
});

it('leaves out the lessons of another class', function () {
    [$tenant] = studentWithClass('kelasku-lain');
    $other = attendanceClass($tenant, 'X 2');
    $slot = attendanceSlot($tenant, ['start_time' => '08:00:00', 'end_time' => '08:45:00']);
    attendanceTeach($tenant, $other, $slot, 'Fisika');

    get(school($tenant->slug, '/absensi/kelasku/jadwal'))->assertInertia(fn ($page) => $page
        ->has('days.0.lessons', 1)
        ->where('days.0.lessons.0.subject', 'Matematika'));

    expect(attendanceSchool($tenant, fn () => TimetableEntry::query()->count()))->toBe(2);
});

it('lists the subjects of the class with their teachers', function () {
    [$tenant] = studentWithClass('kelasku-mapel');

    get(school($tenant->slug, '/absensi/kelasku/mapel'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Attendance/MyClassSubjects')
        ->where('subjects', [['subject' => 'Matematika', 'teacher' => 'Guru Matematika']]));
});

it('gives a student without a class empty pages', function () {
    $tenant = attendanceTenant(role: 'siswa', slug: 'kelasku-kosong');
    attendanceStudent($tenant, attendanceClass($tenant), 'Aditya', ['user_id' => auth()->id(), 'class_id' => null]);

    get(school($tenant->slug, '/absensi/kelasku'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('class', null)
        ->where('classmates', []));
    get(school($tenant->slug, '/absensi/kelasku/jadwal'))->assertInertia(fn ($page) => $page->where('days', []));
});

it('refuses an account that is not linked to a student', function () {
    $tenant = attendanceTenant(role: 'siswa', slug: 'kelasku-tanpa-siswa');

    get(school($tenant->slug, '/absensi/kelasku'))->assertForbidden();
});

it('refuses staff roles', function (string $role, string $path) {
    $tenant = attendanceTenant(role: $role, slug: "kelasku-{$role}");

    get(school($tenant->slug, $path))->assertForbidden();
})->with([
    'guru' => ['guru', '/absensi/kelasku'],
    'admin' => ['admin-sekolah', '/absensi/kelasku/jadwal'],
    'staf' => ['staf-tu', '/absensi/kelasku/mapel'],
]);
