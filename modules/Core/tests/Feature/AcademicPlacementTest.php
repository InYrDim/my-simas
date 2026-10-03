<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\StudentClassHistory;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/Support/helpers.php';

/**
 * A school with an active year (classes X 1 and X 2, two active students in
 * X 1, each with a history row) and a later draft year (class XI 1).
 *
 * @return array{tenant: Tenant, year: AcademicYear, nextYear: AcademicYear, x1: ClassGroup, x2: ClassGroup, xi1: ClassGroup, students: list<Student>}
 */
function placementSetup(string $slug, string $role = 'admin-sekolah'): array
{
    $tenant = schoolAs($slug, $role);
    $year = yearWithSemesters($tenant, 'active', '2025-07-14');
    $nextYear = yearWithSemesters($tenant, 'draft', '2026-07-13');
    $x1 = classIn($tenant, ['academic_year_id' => $year->id, 'name' => 'X 1']);
    $x2 = classIn($tenant, ['academic_year_id' => $year->id, 'name' => 'X 2']);
    $xi1 = classIn($tenant, ['academic_year_id' => $nextYear->id, 'name' => 'XI 1']);

    $students = inSchool($tenant, function () use ($x1, $year): array {
        $rows = [];

        foreach (['Andi', 'Budi'] as $name) {
            $student = Student::factory()->create(['name' => $name, 'class_id' => $x1->id]);
            StudentClassHistory::query()->create([
                'student_id' => $student->id, 'academic_year_id' => $year->id,
                'class_id' => $x1->id, 'class_name' => 'X 1', 'note' => 'Kelas aktif',
            ]);
            $rows[] = $student;
        }

        return $rows;
    });

    return compact('tenant', 'year', 'nextYear', 'x1', 'x2', 'xi1', 'students');
}

it('opens on the first class of the active year with its active students', function () {
    ['tenant' => $tenant, 'x1' => $x1] = placementSetup('pl-index');

    get(school($tenant->slug, '/akademik/penempatan'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Academic/Placement/Index')
        ->where('sourceClassId', $x1->id)
        ->has('years', 2)
        ->has('classes', 3)
        ->has('students', 2)
        ->where('students.0.name', 'Andi')
    );
});

it('promotes students to a class of a later year and writes that years history', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'xi1' => $xi1, 'nextYear' => $nextYear, 'students' => $students] = placementSetup('pl-promote');

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'promote', 'source_class_id' => $x1->id, 'target_class_id' => $xi1->id,
        'student_ids' => [$students[0]->id, $students[1]->id],
    ])->assertRedirect();

    inSchool($tenant, function () use ($students, $xi1, $nextYear): void {
        foreach ($students as $student) {
            expect($student->fresh()->class_id)->toBe($xi1->id)
                ->and($student->fresh()->status)->toBe('active');

            $row = StudentClassHistory::query()->where('student_id', $student->id)->where('academic_year_id', $nextYear->id)->sole();
            expect($row->class_name)->toBe('XI 1');
        }
    });
});

it('moves students to another class of the same year and notes the move', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'x2' => $x2, 'year' => $year, 'students' => $students] = placementSetup('pl-move');

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'move', 'source_class_id' => $x1->id, 'target_class_id' => $x2->id,
        'student_ids' => [$students[0]->id],
    ])->assertRedirect();

    inSchool($tenant, function () use ($students, $x2, $year): void {
        expect($students[0]->fresh()->class_id)->toBe($x2->id);

        $row = StudentClassHistory::query()->where('student_id', $students[0]->id)->where('academic_year_id', $year->id)->sole();
        expect($row->class_name)->toBe('X 2')->and($row->note)->toBe('Pindah kelas');

        expect($students[1]->fresh()->class_id)->not->toBe($x2->id);
    });
});

it('graduates students: no class, graduated status and a Lulus note', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'year' => $year, 'students' => $students] = placementSetup('pl-graduate');

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'graduate', 'source_class_id' => $x1->id, 'student_ids' => [$students[0]->id],
    ])->assertRedirect();

    inSchool($tenant, function () use ($students, $year): void {
        $student = $students[0]->fresh();
        expect($student->status)->toBe('graduated')->and($student->class_id)->toBeNull();

        expect(StudentClassHistory::query()->where('student_id', $student->id)->where('academic_year_id', $year->id)->sole()->note)->toBe('Lulus');
    });
});

it('changes nobody when one selected student is not an active member of the source class', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'x2' => $x2, 'students' => $students] = placementSetup('pl-atomic');
    $outsider = inSchool($tenant, fn () => Student::factory()->create(['class_id' => $x2->id]));

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'graduate', 'source_class_id' => $x1->id, 'student_ids' => [$students[0]->id, $outsider->id],
    ])->assertSessionHasErrors('student_ids');

    inSchool($tenant, function () use ($students, $outsider, $x1, $x2): void {
        expect($students[0]->fresh()->status)->toBe('active')
            ->and($students[0]->fresh()->class_id)->toBe($x1->id)
            ->and($outsider->fresh()->class_id)->toBe($x2->id);
    });
});

it('refuses a target that does not fit the action', function (string $action, string $targetKey, string $error) {
    $setup = placementSetup("pl-bad-{$action}-{$targetKey}");
    $targets = ['x1' => $setup['x1'], 'x2' => $setup['x2'], 'xi1' => $setup['xi1'], 'none' => null];

    post(school($setup['tenant']->slug, '/akademik/penempatan'), array_filter([
        'action' => $action, 'source_class_id' => $setup['x1']->id,
        'target_class_id' => $targets[$targetKey]?->id, 'student_ids' => [$setup['students'][0]->id],
    ], fn (mixed $value): bool => $value !== null))->assertSessionHasErrors($error);

    expect(inSchool($setup['tenant'], fn () => $setup['students'][0]->fresh()->class_id))->toBe($setup['x1']->id);
})->with([
    'promote without target' => ['promote', 'none', 'target_class_id'],
    'promote within the same year' => ['promote', 'x2', 'target_class_id'],
    'move to another year' => ['move', 'xi1', 'target_class_id'],
    'move to the same class' => ['move', 'x1', 'target_class_id'],
]);

it('refuses an empty selection and an unknown action', function () {
    ['tenant' => $tenant, 'x1' => $x1] = placementSetup('pl-validate');

    post(school($tenant->slug, '/akademik/penempatan'), ['action' => 'graduate', 'source_class_id' => $x1->id, 'student_ids' => []])
        ->assertSessionHasErrors('student_ids');

    post(school($tenant->slug, '/akademik/penempatan'), ['action' => 'explode', 'source_class_id' => $x1->id, 'student_ids' => [1]])
        ->assertSessionHasErrors('action');
});

it('refuses classes and students of another school', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'students' => $students] = placementSetup('pl-mine');
    $other = TenantFactory::new()->create(['slug' => 'pl-theirs']);
    $foreignClass = classIn($other);
    $foreignStudent = inSchool($other, fn () => Student::factory()->create(['class_id' => $foreignClass->id]));

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'graduate', 'source_class_id' => $foreignClass->id, 'student_ids' => [$foreignStudent->id],
    ])->assertSessionHasErrors(['source_class_id', 'student_ids.0']);

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'move', 'source_class_id' => $x1->id, 'target_class_id' => $foreignClass->id, 'student_ids' => [$students[0]->id],
    ])->assertSessionHasErrors('target_class_id');

    expect(inSchool($other, fn () => $foreignStudent->fresh()->status))->toBe('active');
});

it('forbids a teacher role from placing students and from the editor page', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'students' => $students] = placementSetup('pl-guru', 'guru');

    get(school($tenant->slug, '/akademik/penempatan'))->assertForbidden();

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'graduate', 'source_class_id' => $x1->id, 'student_ids' => [$students[0]->id],
    ])->assertForbidden();

    expect(inSchool($tenant, fn () => $students[0]->fresh()->status))->toBe('active');
});

it('redirects a guest who tries to place students', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'pl-guest']);

    post(school($tenant->slug, '/akademik/penempatan'), ['action' => 'graduate'])->assertRedirect();
});
