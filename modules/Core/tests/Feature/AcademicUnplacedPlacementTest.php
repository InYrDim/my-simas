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

/*
 * Penempatan Siswa for students who have no class yet (new students, such
 * as those who re-registered through admissions): listing them and giving
 * them a first class.
 */

/**
 * A school with an active year (classes X 1 and X 2), a later draft year
 * (class XI 1), one placed student (Andi, in X 1) and students without a
 * class: Citra and Dedi are active, Eka has graduated.
 *
 * @return array{tenant: Tenant, year: AcademicYear, x1: ClassGroup, x2: ClassGroup, xi1: ClassGroup, placed: Student, unplaced: list<Student>}
 */
function unplacedSetup(string $slug, string $role = 'admin-sekolah'): array
{
    $tenant = schoolAs($slug, $role);
    $year = yearWithSemesters($tenant, 'active', '2025-07-14');
    $nextYear = yearWithSemesters($tenant, 'draft', '2026-07-13');
    $x1 = classIn($tenant, ['academic_year_id' => $year->id, 'name' => 'X 1']);
    $x2 = classIn($tenant, ['academic_year_id' => $year->id, 'name' => 'X 2']);
    $xi1 = classIn($tenant, ['academic_year_id' => $nextYear->id, 'name' => 'XI 1']);

    $placed = inSchool($tenant, fn (): Student => Student::factory()->create(['name' => 'Andi', 'class_id' => $x1->id]));
    $unplaced = inSchool($tenant, fn (): array => [
        Student::factory()->create(['name' => 'Citra', 'class_id' => null]),
        Student::factory()->create(['name' => 'Dedi', 'class_id' => null]),
        Student::factory()->create(['name' => 'Eka', 'class_id' => null, 'status' => 'graduated']),
    ]);

    return compact('tenant', 'year', 'x1', 'x2', 'xi1', 'placed', 'unplaced');
}

it('counts the students without a class and keeps opening on the first class', function () {
    ['tenant' => $tenant, 'x1' => $x1] = unplacedSetup('pl-belum-hitung');

    get(school($tenant->slug, '/akademik/penempatan'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('sourceClassId', $x1->id)
        ->where('unplaced', false)
        ->where('unplacedCount', 2)
        ->has('students', 1)
    );
});

it('lists only active students without a class when asked for the unplaced', function () {
    ['tenant' => $tenant] = unplacedSetup('pl-belum-daftar');

    get(school($tenant->slug, '/akademik/penempatan?kelas=belum'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Academic/Placement/Index')
        ->where('sourceClassId', null)
        ->where('unplaced', true)
        ->where('unplacedCount', 2)
        ->has('students', 2)
        ->where('students.0.name', 'Citra')
        ->where('students.1.name', 'Dedi')
    );
});

it('opens the unplaced list even when the school has no classes yet', function () {
    $tenant = schoolAs('pl-belum-tanpa-kelas');
    inSchool($tenant, fn () => Student::factory()->create(['name' => 'Citra', 'class_id' => null]));

    get(school($tenant->slug, '/akademik/penempatan?kelas=belum'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('unplaced', true)
        ->where('classes', [])
        ->has('students', 1)
    );
});

it('places students without a class into a class and writes that year\'s history', function () {
    ['tenant' => $tenant, 'x2' => $x2, 'year' => $year, 'unplaced' => $unplaced] = unplacedSetup('pl-belum-tempatkan');

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'assign', 'target_class_id' => $x2->id, 'student_ids' => [$unplaced[0]->id, $unplaced[1]->id],
    ])->assertRedirect()->assertSessionHas('status', '2 siswa ditempatkan ke X 2.');

    inSchool($tenant, function () use ($unplaced, $x2, $year): void {
        foreach ([$unplaced[0], $unplaced[1]] as $student) {
            expect($student->fresh()->class_id)->toBe($x2->id);

            $history = StudentClassHistory::query()->where('student_id', $student->id)->where('academic_year_id', $year->id)->sole();

            expect($history->class_id)->toBe($x2->id)->and($history->class_name)->toBe('X 2');
        }
    });
});

it('lets students without a class join a class of a year still to come', function () {
    ['tenant' => $tenant, 'xi1' => $xi1, 'unplaced' => $unplaced] = unplacedSetup('pl-belum-mendatang');

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'assign', 'target_class_id' => $xi1->id, 'student_ids' => [$unplaced[0]->id],
    ])->assertRedirect();

    expect(inSchool($tenant, fn () => $unplaced[0]->fresh()->class_id))->toBe($xi1->id);
});

it('places nobody unless every selected student is active and without a class', function (string $case) {
    ['tenant' => $tenant, 'x1' => $x1, 'x2' => $x2, 'placed' => $placed, 'unplaced' => $unplaced] = unplacedSetup("pl-belum-tolak-{$case}");

    $bad = $case === 'placed' ? $placed : $unplaced[2];

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'assign', 'target_class_id' => $x2->id, 'student_ids' => [$unplaced[0]->id, $bad->id],
    ])->assertSessionHasErrors('student_ids');

    inSchool($tenant, function () use ($unplaced, $placed, $x1): void {
        expect($unplaced[0]->fresh()->class_id)->toBeNull()
            ->and($placed->fresh()->class_id)->toBe($x1->id);
    });
})->with(['placed', 'graduated']);

it('refuses a target class of a finished year or none at all', function () {
    ['tenant' => $tenant, 'unplaced' => $unplaced] = unplacedSetup('pl-belum-arsip');
    $archived = yearWithSemesters($tenant, 'archived', '2023-07-17');
    $old = classIn($tenant, ['academic_year_id' => $archived->id, 'name' => 'Lama']);

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'assign', 'target_class_id' => $old->id, 'student_ids' => [$unplaced[0]->id],
    ])->assertSessionHasErrors('target_class_id');

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'assign', 'student_ids' => [$unplaced[0]->id],
    ])->assertSessionHasErrors('target_class_id');

    expect(inSchool($tenant, fn () => $unplaced[0]->fresh()->class_id))->toBeNull();
});

it('does not place students or into classes of another school', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'unplaced' => $unplaced] = unplacedSetup('pl-belum-mine');
    $other = TenantFactory::new()->create(['slug' => 'pl-belum-theirs']);
    $foreignClass = classIn($other);
    $foreignStudent = inSchool($other, fn () => Student::factory()->create(['class_id' => null]));

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'assign', 'target_class_id' => $foreignClass->id, 'student_ids' => [$unplaced[0]->id],
    ])->assertSessionHasErrors('target_class_id');

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'assign', 'target_class_id' => $x1->id, 'student_ids' => [$foreignStudent->id],
    ])->assertSessionHasErrors('student_ids.0');

    expect(inSchool($other, fn () => $foreignStudent->fresh()->class_id))->toBeNull()
        ->and(inSchool($tenant, fn () => $unplaced[0]->fresh()->class_id))->toBeNull();
});

it('forbids a teacher role from placing students without a class', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'unplaced' => $unplaced] = unplacedSetup('pl-belum-guru', 'guru');

    get(school($tenant->slug, '/akademik/penempatan?kelas=belum'))->assertOk();

    post(school($tenant->slug, '/akademik/penempatan'), [
        'action' => 'assign', 'target_class_id' => $x1->id, 'student_ids' => [$unplaced[0]->id],
    ])->assertForbidden();

    expect(inSchool($tenant, fn () => $unplaced[0]->fresh()->class_id))->toBeNull();
});
