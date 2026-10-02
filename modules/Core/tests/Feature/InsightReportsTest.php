<?php

namespace Modules\Core\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Contracts\ReportRegistry;
use Modules\Core\App\Domain\Actions\SaveStudent;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/insight.php';

/*
 * Laporan: the catalogue, and each report as a CSV download and as a
 * print view, for one academic year of the signed-in school.
 */
beforeEach(fn () => $this->travelTo('2026-10-02 05:00:00'));

it('lists the reports by group with the academic years to choose from', function () {
    $tenant = schoolAs('laporan-katalog', 'guru');
    $past = yearWithSemesters($tenant, 'archived', '2025-07-14');
    $current = yearWithSemesters($tenant, 'active');

    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Insight/Reports')
        ->where('groups', fn ($groups) => collect($groups)->pluck('title')->all() === ['Kesiswaan', 'Akademik', 'Kehadiran', 'Penerimaan (PPDB)'])
        ->where('groups.0.reports', fn ($reports) => collect($reports)->pluck('available', 'key')->all() === ['student-list' => true, 'student-mutation' => true])
        ->where('groups.1.reports', fn ($reports) => collect($reports)->pluck('available', 'key')->all() === ['teaching-load' => true, 'schedule' => false])
        ->where('groups.2.reports', fn ($reports) => collect($reports)->pluck('available')->all() === [false, false])
        ->where('years', [
            ['value' => (string) $current->id, 'label' => '2026/2027 (aktif)'],
            ['value' => (string) $past->id, 'label' => '2025/2026'],
        ])
        ->where('yearId', (string) $current->id)
    );
});

it('downloads the students of every class of the active year', function () {
    $tenant = schoolAs('laporan-siswa', 'guru');
    yearWithSemesters($tenant, 'active');
    $x2 = classIn($tenant, ['name' => 'X 2']);
    $x1 = classIn($tenant, ['name' => 'X 1']);

    placedStudent($tenant, 'Sari Dewi', '0072', $x2, ['gender' => 'P', 'nisn' => '0012345678']);
    placedStudent($tenant, 'Citra Lestari', '0073', $x1, ['gender' => 'P']);
    placedStudent($tenant, 'Budi Santoso', '0071', $x1);
    placedStudent($tenant, 'Tanpa Kelas', '0079', null);

    $response = get(school($tenant->slug, '/statistik-laporan/laporan/student-list/unduh'))
        ->assertOk()
        ->assertDownload('student-list-2026-2027.csv');

    expect(csvRows($response))->toBe([
        ['Kelas', 'NIS', 'NISN', 'Nama', 'L/P', 'Keterangan'],
        ['X 1', '0071', '', 'Budi Santoso', 'L', 'Kelas aktif'],
        ['X 1', '0073', '', 'Citra Lestari', 'P', 'Kelas aktif'],
        ['X 2', '0072', '0012345678', 'Sari Dewi', 'P', 'Kelas aktif'],
    ]);
});

it('reports a past year from the class history of that year', function () {
    $tenant = schoolAs('laporan-tahun-lalu');
    $past = yearWithSemesters($tenant, 'archived', '2025-07-14');
    $current = yearWithSemesters($tenant, 'active');
    $ix = classIn($tenant, ['name' => 'IX 1', 'academic_year_id' => $past->id]);
    $x = classIn($tenant, ['name' => 'X 1', 'academic_year_id' => $current->id]);

    $student = placedStudent($tenant, 'Budi Santoso', '0071', $ix);
    inSchool($tenant, fn () => app(SaveStudent::class)->handle($student, ['class_id' => $x->id]));

    $response = get(school($tenant->slug, "/statistik-laporan/laporan/student-list/unduh?tahun={$past->id}"))
        ->assertDownload('student-list-2025-2026.csv');

    expect(csvRows($response)[1])->toBe(['IX 1', '0071', '', 'Budi Santoso', 'L', 'Kelas aktif']);
});

it('lists the students who changed class or left, not those who stayed', function () {
    $tenant = schoolAs('laporan-mutasi');
    yearWithSemesters($tenant, 'active');
    $x1 = classIn($tenant, ['name' => 'X 1']);
    $x2 = classIn($tenant, ['name' => 'X 2']);

    placedStudent($tenant, 'Tetap Di Kelas', '0070', $x1);
    $moved = placedStudent($tenant, 'Budi Santoso', '0071', $x1);
    $graduated = placedStudent($tenant, 'Sari Dewi', '0072', $x2);

    inSchool($tenant, function () use ($moved, $graduated, $x2): void {
        app(SaveStudent::class)->handle($moved, ['class_id' => $x2->id]);
        $this->travel(1)->minutes();
        app(SaveStudent::class)->handle($graduated, ['status' => 'graduated']);
    });

    $response = get(school($tenant->slug, '/statistik-laporan/laporan/student-mutation/unduh'))
        ->assertDownload('student-mutation-2026-2027.csv');

    expect(csvRows($response))->toBe([
        ['NIS', 'Nama', 'Kelas', 'Keterangan', 'Tanggal'],
        ['0071', 'Budi Santoso', 'X 2', 'Pindah kelas', '02/10/2026'],
        ['0072', 'Sari Dewi', 'X 2', 'Lulus', '02/10/2026'],
    ]);
});

it('sums the classes, subjects and weekly hours of each teacher', function () {
    $tenant = schoolAs('laporan-beban', 'guru');
    $past = yearWithSemesters($tenant, 'archived', '2025-07-14');
    $current = yearWithSemesters($tenant, 'active');
    $old = classIn($tenant, ['name' => 'IX 1', 'academic_year_id' => $past->id]);
    $x1 = classIn($tenant, ['name' => 'X 1', 'academic_year_id' => $current->id]);
    $x2 = classIn($tenant, ['name' => 'X 2', 'academic_year_id' => $current->id]);

    inSchool($tenant, function () use ($old, $x1, $x2): void {
        $siti = Teacher::factory()->create(['name' => 'Siti Aminah', 'nip' => '198501012010012001', 'duty' => 'Guru Mapel']);
        $ahmad = Teacher::factory()->honorary()->create(['name' => 'Ahmad Fauzi', 'duty' => 'Guru Mapel']);
        Teacher::factory()->create(['name' => 'Tanpa Jam']);
        [$math, $physics] = [Subject::factory()->create(), Subject::factory()->create()];

        $assign = fn (ClassGroup $class, Subject $subject, Teacher $teacher, int $hours) => TeachingAssignment::factory()->create([
            'class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'hours_per_week' => $hours,
        ]);

        $assign($x1, $math, $siti, 4);
        $assign($x2, $math, $siti, 4);
        $assign($x1, $physics, $siti, 2);
        $assign($x2, $physics, $ahmad, 3);
        $assign($old, $math, $ahmad, 6);
    });

    $response = get(school($tenant->slug, '/statistik-laporan/laporan/teaching-load/unduh'))
        ->assertDownload('teaching-load-2026-2027.csv');

    expect(csvRows($response))->toBe([
        ['Nama', 'NIP', 'Tugas', 'Jumlah rombel', 'Jumlah mapel', 'Jam per minggu'],
        ['Ahmad Fauzi', '', 'Guru Mapel', '1', '1', '3'],
        ['Siti Aminah', '198501012010012001', 'Guru Mapel', '2', '2', '10'],
    ]);
});

it('shows the same table in the print view', function () {
    $tenant = schoolAs('laporan-cetak', 'guru');
    yearWithSemesters($tenant, 'active');
    placedStudent($tenant, 'Budi Santoso', '0071', classIn($tenant, ['name' => 'X 1']));

    get(school($tenant->slug, '/statistik-laporan/laporan/student-list/cetak'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Insight/ReportPrint')
        ->where('school.name', $tenant->name)
        ->where('title', 'Daftar Siswa per Kelas')
        ->where('period', '2026/2027')
        ->where('printedOn', '2 Oktober 2026')
        ->where('columns', ['Kelas', 'NIS', 'NISN', 'Nama', 'L/P', 'Keterangan'])
        ->where('rows', [['X 1', '0071', null, 'Budi Santoso', 'L', 'Kelas aktif']])
    );
});

it('answers 404 for a report that is unknown or only announced', function (string $key, string $action) {
    $tenant = schoolAs('laporan-404');
    yearWithSemesters($tenant, 'active');

    get(school($tenant->slug, "/statistik-laporan/laporan/{$key}/{$action}"))->assertNotFound();
})->with(['tidak-ada', 'schedule', 'attendance-monthly'])->with(['unduh', 'cetak']);

it('refuses a report the user lacks the permission for', function (string $action) {
    $tenant = schoolAs('laporan-403', 'guru');
    yearWithSemesters($tenant, 'active');
    app(ReportRegistry::class)->register('core', ManagersOnlyReport::class);

    get(school($tenant->slug, "/statistik-laporan/laporan/managers-only/{$action}"))->assertForbidden();
    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => ! collect($groups)->pluck('reports')->flatten(1)->pluck('key')->contains('managers-only'))
    );
})->with(['unduh', 'cetak']);

it('offers the catalogue but no download to a school without an academic year', function () {
    $tenant = schoolAs('laporan-tanpa-tahun');

    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('years', [])
        ->where('yearId', '')
        ->has('groups', 4)
    );

    get(school($tenant->slug, '/statistik-laporan/laporan/student-list/unduh'))->assertNotFound();
});

it('never reports the records or the years of another school', function () {
    $other = TenantFactory::new()->create(['slug' => 'laporan-tetangga']);
    $otherYear = yearWithSemesters($other, 'archived', '2024-07-15');
    placedStudent($other, 'Murid Tetangga', '0071', classIn($other, ['name' => 'X 1']));

    $tenant = schoolAs('laporan-sendiri');
    yearWithSemesters($tenant, 'active');
    placedStudent($tenant, 'Budi Santoso', '0071', classIn($tenant, ['name' => 'X 1']));

    $response = get(school($tenant->slug, "/statistik-laporan/laporan/student-list/unduh?tahun={$otherYear->id}"))
        ->assertDownload('student-list-2026-2027.csv');

    expect(array_column(csvRows($response), 3))->toBe(['Nama', 'Budi Santoso']);
});
