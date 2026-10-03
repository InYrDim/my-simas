<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/**
 * @return array{0: Tenant, 1: ClassGroup, 2: Subject, 3: Teacher}
 */
function assignmentSetup(string $slug, string $role = 'admin-sekolah'): array
{
    $tenant = schoolAs($slug, $role);
    $class = classIn($tenant, ['name' => 'X 1']);
    $subject = inSchool($tenant, fn () => Subject::factory()->create(['code' => 'MTK', 'name' => 'Matematika']));
    $teacher = inSchool($tenant, fn () => Teacher::factory()->create(['name' => 'Pak Budi']));

    return [$tenant, $class, $subject, $teacher];
}

it('lists the active years classes, every subject and the selected classs assignments', function () {
    [$tenant, $first, $subject, $teacher] = assignmentSetup('as-list');
    $second = classIn($tenant, ['name' => 'X 2']);
    inSchool($tenant, fn () => TeachingAssignment::factory()->create([
        'class_id' => $second->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'hours_per_week' => 4,
    ]));

    get(school($tenant->slug, '/akademik/pengampu'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Academic/Assignments/Index')
        ->has('classes', 2)
        ->where('classId', $first->id)
        ->has('subjects', 1)
        ->has('assignments', 0)
    );

    get(school($tenant->slug, "/akademik/pengampu?kelas={$second->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('classId', $second->id)
        ->has('assignments', 1)
        ->where('assignments.0.subject', 'Matematika')
        ->where('assignments.0.teacher', 'Pak Budi')
        ->where('assignments.0.hours', 4)
    );
});

it('saves a classs assignments and shows them on the class and teacher pages', function () {
    [$tenant, $class, $subject, $teacher] = assignmentSetup('as-save');

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), [
        'assignments' => [['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'hours' => 3]],
    ])->assertRedirect();

    $row = inSchool($tenant, fn () => TeachingAssignment::query()->sole());
    expect($row->class_id)->toBe($class->id)
        ->and($row->subject_id)->toBe($subject->id)
        ->and($row->teacher_id)->toBe($teacher->id)
        ->and($row->hours_per_week)->toBe(3);

    get(school($tenant->slug, "/master/kelas/{$class->id}"))->assertInertia(fn (Assert $page) => $page
        ->has('assignments', 1)
        ->where('assignments.0.subject', 'Matematika')
        ->where('assignments.0.teacher', 'Pak Budi')
    );

    get(school($tenant->slug, "/master/guru/{$teacher->id}"))->assertInertia(fn (Assert $page) => $page
        ->has('assignments', 1)
        ->where('assignments.0.class', 'X 1')
    );
});

it('replaces the assignments of the class: changed rows update, omitted rows are removed', function () {
    [$tenant, $class, $subject, $teacher] = assignmentSetup('as-sync');
    $other = inSchool($tenant, fn () => Subject::factory()->create(['code' => 'BIN']));
    $newTeacher = inSchool($tenant, fn () => Teacher::factory()->create());

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), ['assignments' => [
        ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'hours' => 2],
        ['subject_id' => $other->id, 'teacher_id' => $teacher->id, 'hours' => 2],
    ]])->assertRedirect();

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), ['assignments' => [
        ['subject_id' => $subject->id, 'teacher_id' => $newTeacher->id, 'hours' => 5],
    ]])->assertRedirect();

    $rows = inSchool($tenant, fn () => TeachingAssignment::query()->get());
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->teacher_id)->toBe($newTeacher->id)
        ->and($rows->first()->hours_per_week)->toBe(5);

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), ['assignments' => []])->assertRedirect();

    expect(inSchool($tenant, fn () => TeachingAssignment::query()->count()))->toBe(0);
});

it('rejects invalid hours and duplicate subjects and stores nothing', function (array $rows, string $errorKey) {
    [$tenant, $class, $subject, $teacher] = assignmentSetup('as-invalid');

    $payload = array_map(fn (array $row): array => [
        'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'hours' => 2, ...$row,
    ], $rows);

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), ['assignments' => $payload])
        ->assertSessionHasErrors($errorKey);

    expect(inSchool($tenant, fn () => TeachingAssignment::query()->count()))->toBe(0);
})->with([
    'zero hours' => [[['hours' => 0]], 'assignments.0.hours'],
    'too many hours' => [[['hours' => 21]], 'assignments.0.hours'],
    'duplicate subject' => [[[], []], 'assignments.0.subject_id'],
]);

it('refuses a teacher or subject of another school', function () {
    [$tenant, $class, $subject, $teacher] = assignmentSetup('as-own');
    $other = TenantFactory::new()->create(['slug' => 'as-other']);
    $foreignTeacher = inSchool($other, fn () => Teacher::factory()->create());
    $foreignSubject = inSchool($other, fn () => Subject::factory()->create());

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), ['assignments' => [
        ['subject_id' => $subject->id, 'teacher_id' => $foreignTeacher->id, 'hours' => 2],
    ]])->assertSessionHasErrors('assignments.0.teacher_id');

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), ['assignments' => [
        ['subject_id' => $foreignSubject->id, 'teacher_id' => $teacher->id, 'hours' => 2],
    ]])->assertSessionHasErrors('assignments.0.subject_id');

    expect(inSchool($tenant, fn () => TeachingAssignment::query()->count()))->toBe(0);
});

it('answers 404 for a class of another school', function () {
    [$tenant, , $subject, $teacher] = assignmentSetup('as-mine');
    $other = TenantFactory::new()->create(['slug' => 'as-theirs']);
    $foreignClass = classIn($other);

    put(school($tenant->slug, "/akademik/pengampu/{$foreignClass->id}"), ['assignments' => [
        ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'hours' => 2],
    ]])->assertNotFound();

    expect(inSchool($other, fn () => TeachingAssignment::query()->count()))->toBe(0);
});

it('refuses a class outside the active academic year', function () {
    [$tenant, , $subject, $teacher] = assignmentSetup('as-year');
    $draft = yearWithSemesters($tenant, 'draft', '2030-07-13');
    $draftClass = classIn($tenant, ['academic_year_id' => $draft->id, 'name' => 'XI 1']);

    put(school($tenant->slug, "/akademik/pengampu/{$draftClass->id}"), ['assignments' => [
        ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'hours' => 2],
    ]])->assertSessionHasErrors('assignments');

    expect(inSchool($tenant, fn () => TeachingAssignment::query()->count()))->toBe(0);
});

it('refuses to delete a teacher or a subject that is still assigned', function () {
    [$tenant, $class, $subject, $teacher] = assignmentSetup('as-guard');
    inSchool($tenant, fn () => TeachingAssignment::factory()->create([
        'class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
    ]));

    delete(school($tenant->slug, "/master/guru/{$teacher->id}"))->assertSessionHasErrors('status');
    delete(school($tenant->slug, "/master/mata-pelajaran/{$subject->id}"))->assertSessionHasErrors('status');

    expect(inSchool($tenant, fn () => Teacher::query()->whereKey($teacher->id)->exists() && Subject::query()->whereKey($subject->id)->exists()))->toBeTrue();
});

it('removes the assignments of a deleted class', function () {
    [$tenant, $class, $subject, $teacher] = assignmentSetup('as-class');
    inSchool($tenant, fn () => TeachingAssignment::factory()->create([
        'class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
    ]));

    delete(school($tenant->slug, "/master/kelas/{$class->id}"))->assertRedirect();

    expect(inSchool($tenant, fn () => TeachingAssignment::query()->count()))->toBe(0);
});

it('forbids a teacher role from saving and from the editor page', function () {
    [$tenant, $class, $subject, $teacher] = assignmentSetup('as-guru', 'guru');

    get(school($tenant->slug, '/akademik/pengampu'))->assertForbidden();

    put(school($tenant->slug, "/akademik/pengampu/{$class->id}"), ['assignments' => [
        ['subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'hours' => 2],
    ]])->assertForbidden();

    expect(inSchool($tenant, fn () => TeachingAssignment::query()->count()))->toBe(0);
});

it('redirects a guest who tries to save', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'as-guest']);

    put(school($tenant->slug, '/akademik/pengampu/1'), ['assignments' => []])->assertRedirect();
});
