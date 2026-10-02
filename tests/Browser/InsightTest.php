<?php

use Modules\Core\App\Domain\Actions\SaveStudent;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;

require_once __DIR__.'/Support/school.php';

/*
 * Statistik & Laporan (Fase 7) in a real browser: the figures drawn from
 * the school's records, the year select that steers the report links, and
 * the print view. What each report holds, permissions and tenant
 * isolation are covered by the Core feature tests.
 */
it('shows the schools own figures instead of sample data', function () {
    [$page, $tenant] = schoolMemberSignsIn('guru');

    inTenant($tenant, function (): void {
        $year = AcademicYear::factory()->active()->create(['name' => '2026/2027']);
        $grade = Grade::factory()->create(['name' => 'Tingkat Sepuluh', 'sort_order' => 1]);
        $class = ClassGroup::factory()->create(['academic_year_id' => $year->id, 'grade_id' => $grade->id, 'name' => 'X 1']);

        Student::factory()->count(12)->create(['class_id' => $class->id, 'gender' => 'L']);
        Student::factory()->count(9)->create(['class_id' => $class->id, 'gender' => 'P']);
        Teacher::factory()->count(4)->create();
    });

    $page->navigate('/statistik-laporan/statistik')
        ->assertDontSee('Tampilan contoh')
        ->assertSee('Gambaran singkat sekolah pada tahun ajaran 2026/2027.')
        ->assertSee('Siswa aktif')
        ->assertSee('21')
        ->assertSee('Tingkat Sepuluh')
        ->assertSee('Laki-laki')
        ->assertSee('12')
        ->assertSee('Perempuan')
        ->assertSee('Rata-rata kehadiran')
        ->assertSee('Segera hadir')
        ->assertNoJavaScriptErrors();
});

it('points the report links at the chosen academic year and opens the print view', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    [$past, $current] = inTenant($tenant, function (): array {
        $past = AcademicYear::factory()->archived()->create(['name' => '2025/2026', 'start_date' => '2025-07-14', 'end_date' => '2026-06-27']);
        $current = AcademicYear::factory()->active()->create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-26']);
        $grade = Grade::factory()->create();

        $student = app(SaveStudent::class)->handle(null, [
            'name' => 'Budi Santoso', 'nis' => '0071', 'gender' => 'L',
            'class_id' => ClassGroup::factory()->create(['academic_year_id' => $past->id, 'grade_id' => $grade->id, 'name' => 'IX 1'])->id,
        ]);
        app(SaveStudent::class)->handle($student, [
            'class_id' => ClassGroup::factory()->create(['academic_year_id' => $current->id, 'grade_id' => $grade->id, 'name' => 'XI 5'])->id,
        ]);

        return [$past, $current];
    });

    $csvLink = 'a[aria-label="Unduh CSV Daftar Siswa per Kelas"]';

    $page->navigate('/statistik-laporan/laporan')
        ->assertDontSee('Tampilan contoh')
        ->assertDontSee('Terakhir dibuat')
        ->assertSee('Beban Mengajar Guru')
        ->assertSee('Daftar Pendaftar PPDB')
        ->assertSee('Segera hadir')
        ->assertAttribute($csvLink, 'href', "/statistik-laporan/laporan/student-list/unduh?tahun={$current->id}")
        ->click('internal:role=combobox[name="Tahun ajaran"i]')
        ->click('internal:role=option[name="2025/2026"i]')
        ->assertAttribute($csvLink, 'href', "/statistik-laporan/laporan/student-list/unduh?tahun={$past->id}")
        ->assertAttribute('a[aria-label="Cetak Daftar Siswa per Kelas"]', 'href', "/statistik-laporan/laporan/student-list/cetak?tahun={$past->id}")
        ->assertNoJavaScriptErrors();

    // The print link opens a new tab; follow it in this one.
    $page->navigate("/statistik-laporan/laporan/student-list/cetak?tahun={$past->id}")
        ->assertSee('Daftar Siswa per Kelas')
        ->assertSee('Tahun ajaran 2025/2026')
        ->assertSee('Budi Santoso')
        ->assertSee('IX 1')
        ->assertDontSee('XI 5')
        ->assertSee('Cetak')
        ->assertNoJavaScriptErrors();
});
