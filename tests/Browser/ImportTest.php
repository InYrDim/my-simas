<?php

use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;

require_once __DIR__.'/Support/school.php';
require_once __DIR__.'/Support/uploads.php';

/*
 * Impor Data (Fase 6) in a real browser: choosing a file, the preview the
 * server sends back and the import itself. The row checks, permissions and
 * tenant isolation are covered by the Core feature tests.
 */
beforeEach(fn () => acceptBrowserUploads());

/**
 * A CSV file on disk for the browser to pick; the caller deletes it.
 */
function csvFile(string ...$lines): string
{
    $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('impor-', true).'.csv';
    file_put_contents($path, implode("\r\n", $lines)."\r\n");

    return $path;
}

it('imports students from a CSV, skipping the row the preview flags', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    $class = inTenant($tenant, function (): ClassGroup {
        $year = AcademicYear::factory()->active()->create();

        return ClassGroup::factory()->create(['academic_year_id' => $year->id, 'name' => 'X 1']);
    });

    $file = csvFile(
        'sep=;',
        'nama;nis;nisn;jenis_kelamin;tanggal_lahir;nama_wali;telepon_wali;kelas',
        'Budi Santoso;0071;;L;17/05/2010;;;X 1',
        'Sari Dewi;0072;;P;;;;X 9',
    );

    $page->navigate('/kelola/impor')
        ->assertDontSee('Tampilan contoh')
        ->assertButtonDisabled('Lanjut ke pratinjau')
        ->attach('#file', $file)
        ->press('Lanjut ke pratinjau')
        ->assertSee('2 baris terbaca: 1 baru, 0 diperbarui, 1 perlu diperbaiki.')
        ->assertSee('Kelas "X 9" tidak ada di tahun ajaran aktif.')
        ->press('Impor 1 baris yang siap')
        ->assertSee('1 baris ditambahkan, 0 diperbarui, 1 dilewati.')
        ->assertNoJavaScriptErrors();

    $student = inTenant($tenant, fn () => Student::query()->sole());

    expect($student->only(['name', 'nis', 'class_id']))->toBe(['name' => 'Budi Santoso', 'nis' => '0071', 'class_id' => $class->id]);

    $page->click('Lihat daftar Siswa')
        ->assertPathIs('/master/siswa')
        ->assertSee('Budi Santoso');

    unlink($file);
});

it('updates a teacher in upsert mode and shows a file error next to the field', function () {
    [$page, $tenant] = schoolMemberSignsIn();

    inTenant($tenant, fn () => Teacher::factory()->create(['nip' => '198501012010012001', 'name' => 'Siti', 'duty' => 'Guru Mapel']));

    $broken = csvFile('nama;nip', 'Siti Aminah;198501012010012001');
    $file = csvFile(
        'nama;nip;nuptk;status_kepegawaian;tugas;email',
        'Siti Aminah;198501012010012001;;PNS;Kepala Sekolah;',
    );

    $page->navigate('/kelola/impor')
        ->click('internal:role=combobox[name="Jenis data"i]')
        ->click('internal:role=option[name="Guru & Tendik"i]')
        ->click('internal:role=combobox[name="Mode impor"i]')
        ->click('internal:role=option[name="Perbarui yang sudah ada"i]')
        ->attach('#file', $broken)
        ->press('Lanjut ke pratinjau')
        ->assertSee('Kolom wajib tidak ditemukan: status_kepegawaian, tugas.')
        ->attach('#file', $file)
        ->press('Lanjut ke pratinjau')
        ->assertSee('1 baris terbaca: 0 baru, 1 diperbarui, 0 perlu diperbaiki.')
        ->press('Impor 1 baris yang siap')
        ->assertSee('0 baris ditambahkan, 1 diperbarui, 0 dilewati.')
        ->assertNoJavaScriptErrors();

    expect(inTenant($tenant, fn () => Teacher::query()->sole()->only(['name', 'duty'])))
        ->toBe(['name' => 'Siti Aminah', 'duty' => 'Kepala Sekolah']);

    unlink($broken);
    unlink($file);
});
