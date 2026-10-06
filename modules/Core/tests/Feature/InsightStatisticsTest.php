<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * Statistik: the figures come from the school's own records — active
 * students, teachers, the classes of the active academic year.
 */

/**
 * A school with three grades, two classes in the active year 2026/2027
 * (X 1 with 2 boys and 1 girl, XI 1 with 1 girl), one class in the
 * archived year before it, one active student without a class, one
 * graduate and two teachers.
 */
function schoolWithStudents(Tenant $tenant): void
{
    $past = yearWithSemesters($tenant, 'archived', '2025-07-14');
    $current = yearWithSemesters($tenant, 'active');

    inSchool($tenant, function () use ($past, $current): void {
        [$x, $xi] = [
            Grade::factory()->create(['name' => 'X', 'sort_order' => 1]),
            Grade::factory()->create(['name' => 'XI', 'sort_order' => 2]),
        ];
        Grade::factory()->create(['name' => 'XII', 'sort_order' => 3]);

        ClassGroup::factory()->create(['academic_year_id' => $past->id, 'grade_id' => $x->id, 'name' => 'X 1']);
        $x1 = ClassGroup::factory()->create(['academic_year_id' => $current->id, 'grade_id' => $x->id, 'name' => 'X 1']);
        $xi1 = ClassGroup::factory()->create(['academic_year_id' => $current->id, 'grade_id' => $xi->id, 'name' => 'XI 1']);

        Student::factory()->count(2)->create(['class_id' => $x1->id, 'gender' => 'L']);
        Student::factory()->create(['class_id' => $x1->id, 'gender' => 'P']);
        Student::factory()->create(['class_id' => $xi1->id, 'gender' => 'P']);
        Student::factory()->create(['gender' => 'L']);
        Student::factory()->status('graduated')->create(['gender' => 'P']);

        Teacher::factory()->count(2)->create();
    });
}

it('counts the active students, the teachers and the classes of the active year', function () {
    $tenant = schoolAs('statistik-angka', 'staf-tu');
    schoolWithStudents($tenant);

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Insight/Statistics')
        ->where('period', '2026/2027')
        ->where('figures.0', ['key' => 'students', 'label' => 'Siswa aktif', 'value' => 5, 'hint' => null, 'available' => true])
        ->where('figures.1.value', 2)
        ->where('figures.2', ['key' => 'classes', 'label' => 'Kelas', 'value' => 2, 'hint' => '2026/2027', 'available' => true])
    );
});

it('spreads the active students over every grade and over gender', function () {
    $tenant = schoolAs('statistik-sebaran');
    schoolWithStudents($tenant);

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('panels.0.key', 'students-by-grade')
        ->where('panels.0.title', 'Siswa per tingkat')
        ->where('panels.0.kind', 'bars')
        ->where('panels.0.points', [
            ['label' => 'X', 'value' => 3],
            ['label' => 'XI', 'value' => 1],
            ['label' => 'XII', 'value' => 0],
        ])
        ->where('panels.1.kind', 'share')
        ->where('panels.1.points', [
            ['label' => 'Laki-laki', 'value' => 3],
            ['label' => 'Perempuan', 'value' => 2],
        ])
    );
});

it('announces the attendance figures no module provides yet', function () {
    $tenant = schoolAs('statistik-segera');

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->has('figures', 4)
        ->where('figures.3', ['key' => 'attendance-rate', 'label' => 'Rata-rata kehadiran', 'value' => null, 'hint' => null, 'available' => false])
        ->has('panels', 3)
        ->where('panels.2.key', 'attendance-trend')
        ->where('panels.2.available', false)
    );
});

it('opens for a school that has no records yet', function () {
    $tenant = schoolAs('statistik-kosong');

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('period', null)
        ->where('figures.0.value', 0)
        ->where('figures.2.value', 0)
        ->where('panels.0.points', [])
        ->where('panels.1.points', [
            ['label' => 'Laki-laki', 'value' => 0],
            ['label' => 'Perempuan', 'value' => 0],
        ])
    );
});

it('never counts the records of another school', function () {
    schoolWithStudents(TenantFactory::new()->create(['slug' => 'statistik-tetangga']));

    $tenant = schoolAs('statistik-sendiri');
    inSchool($tenant, fn () => Student::factory()->create(['gender' => 'P']));

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('period', null)
        ->where('figures.0.value', 1)
        ->where('figures.1.value', 0)
        ->where('panels.1.points.0.value', 0)
        ->where('panels.1.points.1.value', 1)
    );
});
