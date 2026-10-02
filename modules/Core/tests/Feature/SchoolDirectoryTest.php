<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Contracts\BellSchedule;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\StudentDirectory;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Platform\App\Domain\Models\Tenant;

require_once __DIR__.'/Support/helpers.php';

/*
 * The read contracts feature modules use instead of Core's models:
 * students, classes and the bell schedule of the current school.
 */

function directoryStudent(Tenant $tenant, ClassGroup $class, string $name, string $nis, array $attributes = []): Student
{
    return inSchool($tenant, fn (): Student => Student::factory()->create([
        'name' => $name,
        'nis' => $nis,
        'class_id' => $class->id,
        ...$attributes,
    ]));
}

/**
 * A teacher whose record carries a login account id.
 */
function directoryTeacher(Tenant $tenant, string $name, ?int $userId = null): Teacher
{
    return inSchool($tenant, function () use ($name, $userId): Teacher {
        $teacher = Teacher::factory()->create(['name' => $name]);
        $teacher->forceFill(['user_id' => $userId])->save();

        return $teacher;
    });
}

it('lists the active students of a class by name', function () {
    $tenant = schoolAs('dir-kelas');
    $class = classIn($tenant, ['name' => 'X 1']);
    $other = classIn($tenant, ['name' => 'X 2']);
    directoryStudent($tenant, $class, 'Zahra', '5002');
    directoryStudent($tenant, $class, 'Adit', '5001');
    directoryStudent($tenant, $other, 'Bima', '5003');
    inSchool($tenant, fn () => Student::factory()->status('graduated')->create(['name' => 'Lulus', 'nis' => '5004']));

    $students = inSchool($tenant, fn () => app(StudentDirectory::class)->ofClass($class->id));

    expect(array_column($students, 'name'))->toBe(['Adit', 'Zahra'])
        ->and($students[0]->nis)->toBe('5001')
        ->and($students[0]->classId)->toBe($class->id)
        ->and($students[0]->className)->toBe('X 1')
        ->and($students[0]->active)->toBeTrue();
});

it('finds a student by id, by account and in bulk', function () {
    $tenant = schoolAs('dir-cari');
    $class = classIn($tenant);
    $adit = directoryStudent($tenant, $class, 'Adit', '5001');
    $zahra = directoryStudent($tenant, $class, 'Zahra', '5002');
    inSchool($tenant, fn () => $adit->forceFill(['user_id' => 77])->save());

    $directory = app(StudentDirectory::class);

    expect(inSchool($tenant, fn () => $directory->find($adit->id))->name)->toBe('Adit')
        ->and(inSchool($tenant, fn () => $directory->find(999999)))->toBeNull()
        ->and(inSchool($tenant, fn () => $directory->findByUserId(77))->id)->toBe($adit->id)
        ->and(inSchool($tenant, fn () => $directory->findByUserId(78)))->toBeNull()
        ->and(array_keys(inSchool($tenant, fn () => $directory->many([$zahra->id, $adit->id, 999999]))))->toEqualCanonicalizing([$adit->id, $zahra->id])
        ->and(inSchool($tenant, fn () => $directory->many([])))->toBe([]);
});

it('searches active students by name or NIS', function () {
    $tenant = schoolAs('dir-telusur');
    $class = classIn($tenant);
    directoryStudent($tenant, $class, 'Rahmat Hidayat', '7001');
    directoryStudent($tenant, $class, 'Siti Rahma', '7002');
    directoryStudent($tenant, $class, 'Bima', '8003');
    inSchool($tenant, fn () => Student::factory()->status('left')->create(['name' => 'Rahman Keluar', 'nis' => '7004']));

    $directory = app(StudentDirectory::class);

    expect(array_column(inSchool($tenant, fn () => $directory->search('rahm')), 'name'))->toBe(['Rahmat Hidayat', 'Siti Rahma'])
        ->and(array_column(inSchool($tenant, fn () => $directory->search('8003')), 'name'))->toBe(['Bima'])
        ->and(inSchool($tenant, fn () => $directory->search('rahm', 1)))->toHaveCount(1)
        ->and(inSchool($tenant, fn () => $directory->search('  ')))->toBe([])
        ->and(inSchool($tenant, fn () => $directory->search('%')))->toBe([]);
});

it('lists the classes of the active year with their active students', function () {
    $tenant = schoolAs('dir-rombel');
    $class = classIn($tenant, ['name' => 'X 2']);
    classIn($tenant, ['name' => 'X 10']);
    classIn($tenant, ['name' => 'X 1']);
    $homeroom = directoryTeacher($tenant, 'Bu Rina');
    inSchool($tenant, fn () => $class->forceFill(['homeroom_teacher_id' => $homeroom->id])->save());
    directoryStudent($tenant, $class, 'Adit', '5001');
    directoryStudent($tenant, $class, 'Keluar', '5002', ['status' => 'left']);

    $old = inSchool($tenant, fn () => ClassGroup::factory()->create([
        'academic_year_id' => AcademicYear::factory()->archived()->create()->id,
        'grade_id' => $class->grade_id,
        'name' => 'X Lama',
    ]));

    $directory = app(ClassDirectory::class);
    $classes = inSchool($tenant, fn () => $directory->ofActiveYear());

    expect(array_column($classes, 'name'))->toBe(['X 1', 'X 2', 'X 10'])
        ->and($classes[1]->homeroomName)->toBe('Bu Rina')
        ->and($classes[1]->studentCount)->toBe(1)
        ->and($classes[0]->homeroomName)->toBeNull()
        ->and(array_column(inSchool($tenant, fn () => $directory->ofYear($old->academic_year_id)), 'name'))->toBe(['X Lama'])
        ->and(inSchool($tenant, fn () => $directory->find($old->id))->academicYearId)->toBe($old->academic_year_id)
        ->and(inSchool($tenant, fn () => $directory->find(999999)))->toBeNull();
});

it('has no classes of the active year for a school without one', function () {
    $tenant = schoolAs('dir-tanpa-tahun');

    expect(inSchool($tenant, fn () => app(ClassDirectory::class)->ofActiveYear()))->toBe([])
        ->and(inSchool($tenant, fn () => app(ClassDirectory::class)->idsTaughtBy(1)))->toBe([]);
});

it('lists the subjects of a class with the teacher and the teacher account', function () {
    $tenant = schoolAs('dir-mapel');
    $class = classIn($tenant);
    $withAccount = directoryTeacher($tenant, 'Pak Budi', 41);
    $withoutAccount = directoryTeacher($tenant, 'Bu Sari');

    inSchool($tenant, function () use ($class, $withAccount, $withoutAccount): void {
        TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => Subject::factory()->create(['name' => 'Matematika'])->id, 'teacher_id' => $withAccount->id]);
        TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => Subject::factory()->create(['name' => 'Bahasa Indonesia'])->id, 'teacher_id' => $withoutAccount->id]);
    });

    $subjects = inSchool($tenant, fn () => app(ClassDirectory::class)->subjectsOf($class->id));

    expect(array_column($subjects, 'subjectName'))->toBe(['Bahasa Indonesia', 'Matematika'])
        ->and($subjects[0]->teacherName)->toBe('Bu Sari')
        ->and($subjects[0]->teacherUserId)->toBeNull()
        ->and($subjects[1]->teacherId)->toBe($withAccount->id)
        ->and($subjects[1]->teacherUserId)->toBe(41);
});

it('knows the classes a teacher account teaches or leads', function () {
    $tenant = schoolAs('dir-ampu');
    $taught = classIn($tenant, ['name' => 'X 1']);
    $led = classIn($tenant, ['name' => 'X 2']);
    classIn($tenant, ['name' => 'X 3']);
    $teacher = directoryTeacher($tenant, 'Pak Budi', 41);

    $old = inSchool($tenant, function () use ($taught, $led, $teacher): ClassGroup {
        TeachingAssignment::factory()->create(['class_id' => $taught->id, 'subject_id' => Subject::factory()->create()->id, 'teacher_id' => $teacher->id]);
        $led->forceFill(['homeroom_teacher_id' => $teacher->id])->save();

        $old = ClassGroup::factory()->create([
            'academic_year_id' => AcademicYear::factory()->archived()->create()->id,
            'grade_id' => $taught->grade_id,
        ]);
        TeachingAssignment::factory()->create(['class_id' => $old->id, 'subject_id' => Subject::factory()->create()->id, 'teacher_id' => $teacher->id]);

        return $old;
    });

    $directory = app(ClassDirectory::class);

    expect(inSchool($tenant, fn () => $directory->idsTaughtBy(41)))->toBe([$taught->id, $led->id])
        ->and(inSchool($tenant, fn () => $directory->idsTaughtBy(41)))->not->toContain($old->id)
        ->and(inSchool($tenant, fn () => $directory->idsTaughtBy(42)))->toBe([]);
});

it('reads the bell schedule of a weekday', function () {
    $tenant = schoolAs('dir-jam');

    $break = inSchool($tenant, function (): PeriodSlot {
        PeriodSlot::factory()->create(['day' => 1, 'start_time' => '08:00:00', 'end_time' => '08:45:00']);
        PeriodSlot::factory()->create(['day' => 1, 'start_time' => '07:15:00', 'end_time' => '08:00:00']);
        PeriodSlot::factory()->create(['day' => 2, 'start_time' => '07:15:00', 'end_time' => '08:00:00']);

        return PeriodSlot::factory()->create(['day' => 1, 'start_time' => '08:45:00', 'end_time' => '09:00:00', 'type' => 'Istirahat']);
    });

    $schedule = app(BellSchedule::class);
    $monday = inSchool($tenant, fn () => $schedule->slotsOn(1));

    expect(array_column($monday, 'startsAt'))->toBe(['07:15', '08:00', '08:45'])
        ->and($monday[0]->endsAt)->toBe('08:00')
        ->and($monday[0]->isLesson)->toBeTrue()
        ->and($monday[2]->isLesson)->toBeFalse()
        ->and(inSchool($tenant, fn () => $schedule->slotsOn(7)))->toBe([])
        ->and(inSchool($tenant, fn () => $schedule->find($break->id))->type)->toBe('Istirahat')
        ->and(inSchool($tenant, fn () => $schedule->find(999999)))->toBeNull();
});

it('never reads another school', function () {
    $other = schoolAs('dir-lain');
    $otherClass = classIn($other, ['name' => 'X 1']);
    $otherStudent = directoryStudent($other, $otherClass, 'Rahmat Lain', '7001', ['user_id' => null]);
    $otherSlot = inSchool($other, fn () => PeriodSlot::factory()->create());
    inSchool($other, fn () => $otherStudent->forceFill(['user_id' => 77])->save());

    $tenant = schoolAs('dir-sendiri');
    classIn($tenant, ['name' => 'X 9']);

    $students = app(StudentDirectory::class);
    $classes = app(ClassDirectory::class);

    expect(inSchool($tenant, fn () => $students->find($otherStudent->id)))->toBeNull()
        ->and(inSchool($tenant, fn () => $students->findByUserId(77)))->toBeNull()
        ->and(inSchool($tenant, fn () => $students->many([$otherStudent->id])))->toBe([])
        ->and(inSchool($tenant, fn () => $students->ofClass($otherClass->id)))->toBe([])
        ->and(inSchool($tenant, fn () => $students->search('Rahmat')))->toBe([])
        ->and(inSchool($tenant, fn () => $classes->find($otherClass->id)))->toBeNull()
        ->and(array_column(inSchool($tenant, fn () => $classes->ofActiveYear()), 'name'))->toBe(['X 9'])
        ->and(inSchool($tenant, fn () => app(BellSchedule::class)->find($otherSlot->id)))->toBeNull()
        ->and(inSchool($tenant, fn () => app(BellSchedule::class)->slotsOn(1)))->toBe([]);
});
