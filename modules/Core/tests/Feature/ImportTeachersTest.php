<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Domain\Models\Teacher;
use Modules\Platform\Database\Factories\TenantFactory;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/import.php';

const TEACHER_HEADER = 'nama,nip,nuptk,status_kepegawaian,tugas,email';

it('imports teachers', function () {
    $tenant = schoolAs('impor-guru');

    importCsv($tenant, 'teachers', [
        TEACHER_HEADER,
        'Siti Aminah,198501012010012001,1234567890123456,PNS,Guru Mapel,siti@sekolah.test',
        'Joko Susilo,,,honorer,tenaga kependidikan,',
    ])->assertOk()->assertExactJson(['created' => 2, 'updated' => 0, 'skipped' => 0]);

    inSchool($tenant, function (): void {
        expect(Teacher::query()->where('nip', '198501012010012001')->sole()->only(['name', 'nuptk', 'employment', 'duty', 'email']))->toBe([
            'name' => 'Siti Aminah', 'nuptk' => '1234567890123456', 'employment' => 'PNS',
            'duty' => 'Guru Mapel', 'email' => 'siti@sekolah.test',
        ]);

        // Options are stored the way the form stores them, whatever the case typed.
        expect(Teacher::query()->where('name', 'Joko Susilo')->sole()->only(['nip', 'nuptk', 'employment', 'duty', 'email']))->toBe([
            'nip' => null, 'nuptk' => null, 'employment' => 'Honorer', 'duty' => 'Tenaga Kependidikan', 'email' => null,
        ]);
    });
});

it('previews a teacher file without writing anything', function () {
    $tenant = schoolAs('impor-guru-pratinjau');

    previewCsv($tenant, 'teachers', [
        TEACHER_HEADER,
        'Siti Aminah,198501012010012001,,PNS,guru mapel,',
    ])->assertOk()->assertExactJson([
        'summary' => ['total' => 1, 'create' => 1, 'update' => 0, 'error' => 0],
        'rows' => [
            ['line' => 2, 'key' => '198501012010012001', 'name' => 'Siti Aminah', 'detail' => 'Guru Mapel', 'outcome' => 'create', 'messages' => []],
        ],
    ]);

    expect(inSchool($tenant, fn () => Teacher::query()->count()))->toBe(0);
});

it('skips a teacher row that fails a check and says why', function (string $row, string $message) {
    $tenant = schoolAs('impor-guru-galat');

    inSchool($tenant, fn () => Teacher::factory()->create(['nip' => '197001011995011001']));

    $lines = [TEACHER_HEADER, 'Siti Aminah,198501012010012001,,PNS,Guru Mapel,', $row];

    previewCsv($tenant, 'teachers', $lines)
        ->assertJsonPath('rows.1.outcome', 'error')
        ->assertJsonPath('rows.1.messages', [$message]);

    importCsv($tenant, 'teachers', $lines)->assertExactJson(['created' => 1, 'updated' => 0, 'skipped' => 1]);

    expect(inSchool($tenant, fn () => Teacher::query()->count()))->toBe(2);
})->with([
    'NIP already registered' => ['Joko,197001011995011001,,PNS,Guru Mapel,', 'NIP sudah terdaftar.'],
    'NIP twice in the file' => ['Joko,198501012010012001,,PNS,Guru Mapel,', 'NIP sudah muncul di baris 2.'],
    'NIP mangled by a spreadsheet' => ['Joko,"1,98501E+17",,PNS,Guru Mapel,', 'NIP terbaca sebagai notasi ilmiah (1,98501E+17). Format kolomnya sebagai Teks di Excel, lalu isi ulang.'],
    'no name' => [',,,PNS,Guru Mapel,', 'Nama wajib diisi.'],
    'no employment' => ['Joko,,,,Guru Mapel,', 'Status kepegawaian wajib diisi.'],
    'unknown employment' => ['Joko,,,Kontrak,Guru Mapel,', 'Status kepegawaian harus salah satu dari: PNS, GTY, GTT, Honorer.'],
    'unknown duty' => ['Joko,,,PNS,Satpam,', 'Tugas harus salah satu dari: Guru Mapel, Tenaga Kependidikan, Kepala Sekolah.'],
    'bad email' => ['Joko,,,PNS,Guru Mapel,joko-at-sekolah', 'Email harus berupa alamat email yang valid.'],
]);

it('always adds a teacher without a NIP', function () {
    $tenant = schoolAs('impor-guru-tanpa-nip');

    $lines = [TEACHER_HEADER, 'Joko Susilo,,,Honorer,Guru Mapel,', 'Joko Susilo,,,Honorer,Guru Mapel,'];

    importCsv($tenant, 'teachers', $lines)->assertExactJson(['created' => 2, 'updated' => 0, 'skipped' => 0]);
    importCsv($tenant, 'teachers', $lines, 'upsert')->assertExactJson(['created' => 2, 'updated' => 0, 'skipped' => 0]);

    expect(inSchool($tenant, fn () => Teacher::query()->count()))->toBe(4);
});

it('refuses a teacher file without its required columns', function () {
    $tenant = schoolAs('impor-guru-berkas');

    importCsv($tenant, 'teachers', ['nama,nip', 'Siti,198501012010012001'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', 'Kolom wajib tidak ditemukan: status_kepegawaian, tugas.');
});

it('refuses roles that cannot manage master data', function (string $role) {
    $tenant = schoolAs("impor-guru-{$role}", $role);

    $lines = [TEACHER_HEADER, 'Siti Aminah,198501012010012001,,PNS,Guru Mapel,'];

    previewCsv($tenant, 'teachers', $lines)->assertForbidden();
    importCsv($tenant, 'teachers', $lines)->assertForbidden();

    expect(inSchool($tenant, fn () => Teacher::query()->count()))->toBe(0);
})->with(['guru', 'staf-tu']);

it('keeps schools apart', function () {
    $other = TenantFactory::new()->create(['slug' => 'impor-guru-lain']);
    inSchool($other, fn () => Teacher::factory()->create(['nip' => '198501012010012001', 'name' => 'Guru Sekolah Lain']));

    $tenant = schoolAs('impor-guru-isolasi');

    importCsv($tenant, 'teachers', [TEACHER_HEADER, 'Siti Aminah,198501012010012001,,PNS,Guru Mapel,'], 'upsert')
        ->assertExactJson(['created' => 1, 'updated' => 0, 'skipped' => 0]);

    expect(inSchool($tenant, fn () => Teacher::query()->sole()->name))->toBe('Siti Aminah')
        ->and(inSchool($other, fn () => Teacher::query()->sole()->name))->toBe('Guru Sekolah Lain');
});

it('updates registered teachers in upsert mode', function () {
    $tenant = schoolAs('impor-guru-perbarui');

    inSchool($tenant, fn () => Teacher::factory()->create([
        'nip' => '198501012010012001', 'name' => 'Siti', 'nuptk' => '1234567890123456',
        'employment' => 'GTT', 'duty' => 'Guru Mapel', 'email' => 'siti@sekolah.test',
    ]));

    $lines = [TEACHER_HEADER, 'Siti Aminah,198501012010012001,,PNS,Kepala Sekolah,'];

    previewCsv($tenant, 'teachers', $lines, 'upsert')
        ->assertJsonPath('summary', ['total' => 1, 'create' => 0, 'update' => 1, 'error' => 0]);

    importCsv($tenant, 'teachers', $lines, 'upsert')->assertExactJson(['created' => 0, 'updated' => 1, 'skipped' => 0]);

    // Cells left empty (NUPTK, email) keep what was stored.
    expect(inSchool($tenant, fn () => Teacher::query()->sole()->only(['name', 'nuptk', 'employment', 'duty', 'email'])))->toBe([
        'name' => 'Siti Aminah', 'nuptk' => '1234567890123456', 'employment' => 'PNS',
        'duty' => 'Kepala Sekolah', 'email' => 'siti@sekolah.test',
    ]);
});
