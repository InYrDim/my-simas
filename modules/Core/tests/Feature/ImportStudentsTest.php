<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Http\UploadedFile;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\StudentClassHistory;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\post;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/import.php';

const STUDENT_HEADER = 'nama,nis,nisn,jenis_kelamin,tanggal_lahir,nama_wali,telepon_wali,kelas';

/**
 * A school with an active academic year and the classes X 1 and X 2.
 *
 * @return array{tenant: Tenant, x1: ClassGroup, x2: ClassGroup}
 */
function importSchool(string $slug, string $role = 'admin-sekolah'): array
{
    $tenant = schoolAs($slug, $role);
    $year = yearWithSemesters($tenant, 'active', '2025-07-14');
    $x1 = classIn($tenant, ['academic_year_id' => $year->id, 'name' => 'X 1']);
    $x2 = classIn($tenant, ['academic_year_id' => $year->id, 'name' => 'X 2']);

    return compact('tenant', 'x1', 'x2');
}

it('imports students, places them in their class and writes the class history', function () {
    ['tenant' => $tenant, 'x1' => $x1] = importSchool('impor-siswa');

    importCsv($tenant, 'students', [
        STUDENT_HEADER,
        'Budi Santoso,0071,0012345678,L,2010-05-17,Slamet,081234567890,X 1',
        'Sari Dewi,0072,,P,,,,',
    ])->assertOk()->assertExactJson(['created' => 2, 'updated' => 0, 'skipped' => 0]);

    inSchool($tenant, function () use ($x1): void {
        $budi = Student::query()->where('nis', '0071')->sole();

        expect($budi->only(['name', 'nisn', 'gender', 'guardian_name', 'guardian_phone', 'status', 'class_id']))->toBe([
            'name' => 'Budi Santoso', 'nisn' => '0012345678', 'gender' => 'L', 'guardian_name' => 'Slamet',
            'guardian_phone' => '081234567890', 'status' => 'active', 'class_id' => $x1->id,
        ])->and($budi->birth_date?->format('Y-m-d'))->toBe('2010-05-17');

        expect(StudentClassHistory::query()->where('student_id', $budi->id)->sole()->only(['class_id', 'class_name', 'note']))
            ->toBe(['class_id' => $x1->id, 'class_name' => 'X 1', 'note' => 'Kelas aktif']);

        $sari = Student::query()->where('nis', '0072')->sole();

        expect($sari->only(['nisn', 'birth_date', 'guardian_name', 'class_id']))
            ->toBe(['nisn' => null, 'birth_date' => null, 'guardian_name' => null, 'class_id' => null])
            ->and(StudentClassHistory::query()->where('student_id', $sari->id)->exists())->toBeFalse();
    });
});

it('previews what the import would do without writing anything', function () {
    ['tenant' => $tenant] = importSchool('impor-pratinjau');

    previewCsv($tenant, 'students', [
        STUDENT_HEADER,
        'Budi Santoso,0071,,L,,,,X 1',
        ',0072,,P,,,,',
    ])->assertOk()->assertExactJson([
        'summary' => ['total' => 2, 'create' => 1, 'update' => 0, 'error' => 1],
        'rows' => [
            ['line' => 2, 'key' => '0071', 'name' => 'Budi Santoso', 'detail' => 'X 1', 'outcome' => 'create', 'messages' => []],
            ['line' => 3, 'key' => '0072', 'name' => '', 'detail' => '', 'outcome' => 'error', 'messages' => ['Nama wajib diisi.']],
        ],
    ]);

    expect(inSchool($tenant, fn () => Student::query()->count()))->toBe(0);
});

it('skips a row that fails a check and says why', function (string $row, string $message) {
    ['tenant' => $tenant] = importSchool('impor-galat');

    inSchool($tenant, fn () => Student::factory()->create(['nis' => '0001', 'nisn' => '9990001']));

    $lines = [STUDENT_HEADER, 'Budi Santoso,0071,8880071,L,,,,X 1', $row];

    previewCsv($tenant, 'students', $lines)
        ->assertJsonPath('rows.1.outcome', 'error')
        ->assertJsonPath('rows.1.messages', [$message]);

    importCsv($tenant, 'students', $lines)->assertExactJson(['created' => 1, 'updated' => 0, 'skipped' => 1]);

    expect(inSchool($tenant, fn () => Student::query()->orderBy('nis')->pluck('nis')->all()))->toBe(['0001', '0071']);
})->with([
    'NIS already registered' => ['Sari,0001,,P,,,,', 'NIS sudah terdaftar.'],
    'NIS twice in the file' => ['Sari,0071,,P,,,,', 'NIS sudah muncul di baris 2.'],
    'NISN of another student' => ['Sari,0072,9990001,P,,,,', 'NISN sudah dipakai siswa lain.'],
    'NISN twice in the file' => ['Sari,0072,8880071,P,,,,', 'NISN sudah muncul di baris 2.'],
    'unknown class' => ['Sari,0072,,P,,,,X 9', 'Kelas "X 9" tidak ada di tahun ajaran aktif.'],
    'no name' => [',0072,,P,,,,', 'Nama wajib diisi.'],
    'no NIS' => ['Sari,,,P,,,,', 'NIS wajib diisi.'],
    'no gender' => ['Sari,0072,,,,,,', 'Jenis kelamin wajib diisi.'],
    'unknown gender' => ['Sari,0072,,X,,,,', 'Jenis kelamin harus L atau P.'],
    'not a date' => ['Sari,0072,,P,31-02-2010,,,', 'Tanggal lahir tidak valid (pakai TTTT-BB-HH atau HH/BB/TTTT).'],
    'birth date in the future' => ['Sari,0072,,P,2999-01-01,,,', 'Tanggal lahir tidak boleh melewati hari ini.'],
    'NIS too long' => ['Sari,'.str_repeat('7', 33).',,P,,,,', 'NIS terlalu panjang (maksimal 32).'],
    'NIS mangled by a spreadsheet' => ['Sari,"1,23E+17",,P,,,,', 'NIS terbaca sebagai notasi ilmiah (1,23E+17). Format kolomnya sebagai Teks di Excel, lalu isi ulang.'],
]);

it('accepts the spellings a spreadsheet user types', function () {
    ['tenant' => $tenant, 'x2' => $x2] = importSchool('impor-ejaan');

    importCsv($tenant, 'students', [
        'sep=;',
        'Nama;NIS;Jenis Kelamin;Tanggal Lahir;Kelas',
        'Budi;0071;laki-laki;17/05/2010;x 2',
        'Sari;0072;Perempuan;2011-01-09;',
    ])->assertExactJson(['created' => 2, 'updated' => 0, 'skipped' => 0]);

    inSchool($tenant, function () use ($x2): void {
        $budi = Student::query()->where('nis', '0071')->sole();

        expect($budi->gender)->toBe('L')
            ->and($budi->birth_date?->format('Y-m-d'))->toBe('2010-05-17')
            ->and($budi->class_id)->toBe($x2->id)
            ->and(Student::query()->where('nis', '0072')->sole()->gender)->toBe('P');
    });
});

it('refuses a file it cannot read as a whole', function (array $lines, string $message) {
    ['tenant' => $tenant] = importSchool('impor-berkas');

    foreach (['previewCsv', 'importCsv'] as $send) {
        (__NAMESPACE__.'\\'.$send)($tenant, 'students', $lines)
            ->assertUnprocessable()
            ->assertJsonPath('errors.file.0', $message);
    }

    expect(inSchool($tenant, fn () => Student::query()->count()))->toBe(0);
})->with([
    'missing required columns' => [['nama,kelas', 'Budi,X 1'], 'Kolom wajib tidak ditemukan: nis, jenis_kelamin.'],
    'header only' => [[STUDENT_HEADER], 'Berkas tidak berisi baris data.'],
]);

it('validates the upload itself', function () {
    ['tenant' => $tenant] = importSchool('impor-unggah');

    post(school($tenant->slug, '/kelola/impor'), [
        'target' => 'classes',
        'mode' => 'replace',
        'file' => UploadedFile::fake()->image('foto.png'),
    ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['target', 'mode', 'file' => 'Berkas harus berupa CSV.']);

    post(school($tenant->slug, '/kelola/impor/pratinjau'), ['target' => 'students', 'mode' => 'add'], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file' => 'Pilih berkas CSV terlebih dahulu.']);
});

it('refuses roles that cannot manage master data', function (string $role) {
    ['tenant' => $tenant] = importSchool("impor-tulis-{$role}", $role);

    $lines = [STUDENT_HEADER, 'Budi Santoso,0071,,L,,,,X 1'];

    previewCsv($tenant, 'students', $lines)->assertForbidden();
    importCsv($tenant, 'students', $lines)->assertForbidden();

    expect(inSchool($tenant, fn () => Student::query()->count()))->toBe(0);
})->with(['guru', 'staf-tu']);

it('keeps schools apart', function () {
    $other = TenantFactory::new()->create(['slug' => 'impor-lain']);
    classIn($other, ['name' => 'XI 9']);
    inSchool($other, fn () => Student::factory()->create(['nis' => '0071', 'nisn' => '9990071']));

    ['tenant' => $tenant] = importSchool('impor-isolasi');

    importCsv($tenant, 'students', [
        STUDENT_HEADER,
        'Budi Santoso,0071,9990071,L,,,,X 1',
        'Sari Dewi,0072,,P,,,,XI 9',
    ])->assertExactJson(['created' => 1, 'updated' => 0, 'skipped' => 1]);

    expect(inSchool($tenant, fn () => Student::query()->pluck('nis')->all()))->toBe(['0071'])
        ->and(inSchool($other, fn () => Student::query()->count()))->toBe(1);
});

it('updates registered students in upsert mode and adds the others', function () {
    ['tenant' => $tenant, 'x1' => $x1, 'x2' => $x2] = importSchool('impor-perbarui');

    importCsv($tenant, 'students', [
        STUDENT_HEADER,
        'Budi Santoso,0071,8880071,L,2010-05-17,Slamet,081234567890,X 1',
    ])->assertExactJson(['created' => 1, 'updated' => 0, 'skipped' => 0]);

    $lines = [
        STUDENT_HEADER,
        'Budi S. Santoso,0071,,L,,Slamet Riyadi,,X 2',
        'Sari Dewi,0072,,P,,,,X 1',
    ];

    previewCsv($tenant, 'students', $lines, 'upsert')
        ->assertJsonPath('summary', ['total' => 2, 'create' => 1, 'update' => 1, 'error' => 0])
        ->assertJsonPath('rows.0.outcome', 'update');

    importCsv($tenant, 'students', $lines, 'upsert')->assertExactJson(['created' => 1, 'updated' => 1, 'skipped' => 0]);

    inSchool($tenant, function () use ($x1, $x2): void {
        $budi = Student::query()->where('nis', '0071')->sole();

        // Cells left empty (NISN, birth date, phone) keep what was stored.
        expect($budi->only(['name', 'nisn', 'guardian_name', 'guardian_phone', 'class_id']))->toBe([
            'name' => 'Budi S. Santoso', 'nisn' => '8880071', 'guardian_name' => 'Slamet Riyadi',
            'guardian_phone' => '081234567890', 'class_id' => $x2->id,
        ])->and($budi->birth_date?->format('Y-m-d'))->toBe('2010-05-17');

        expect(StudentClassHistory::query()->where('student_id', $budi->id)->sole()->only(['class_name', 'note']))
            ->toBe(['class_name' => 'X 2', 'note' => 'Pindah kelas'])
            ->and(Student::query()->where('nis', '0072')->sole()->class_id)->toBe($x1->id)
            ->and(Student::query()->count())->toBe(2);
    });
});

it('leaves the class alone when an update has no class cell', function () {
    ['tenant' => $tenant, 'x1' => $x1] = importSchool('impor-kelas-tetap');

    importCsv($tenant, 'students', [STUDENT_HEADER, 'Budi,0071,,L,,,,X 1']);
    importCsv($tenant, 'students', [STUDENT_HEADER, 'Budi Santoso,0071,,L,,,,'], 'upsert')
        ->assertExactJson(['created' => 0, 'updated' => 1, 'skipped' => 0]);

    inSchool($tenant, function () use ($x1): void {
        expect(Student::query()->sole()->only(['name', 'class_id']))->toBe(['name' => 'Budi Santoso', 'class_id' => $x1->id]);
    });
});

it('does not give a class back to a student who has left', function () {
    ['tenant' => $tenant] = importSchool('impor-nonaktif');

    inSchool($tenant, fn () => Student::factory()->status('graduated')->create(['nis' => '0071', 'name' => 'Budi']));

    importCsv($tenant, 'students', [STUDENT_HEADER, 'Budi Santoso,0071,,L,,,,X 1'], 'upsert')
        ->assertExactJson(['created' => 0, 'updated' => 1, 'skipped' => 0]);

    inSchool($tenant, function (): void {
        expect(Student::query()->sole()->only(['name', 'status', 'class_id']))
            ->toBe(['name' => 'Budi Santoso', 'status' => 'graduated', 'class_id' => null]);
    });
});

it('lets an update keep its own NISN but not take another students', function () {
    ['tenant' => $tenant] = importSchool('impor-nisn');

    inSchool($tenant, function (): void {
        Student::factory()->create(['nis' => '0071', 'nisn' => '8880071']);
        Student::factory()->create(['nis' => '0072', 'nisn' => '8880072']);
    });

    importCsv($tenant, 'students', [
        STUDENT_HEADER,
        'Budi,0071,8880071,L,,,,',
        'Sari,0072,8880071,P,,,,',
    ], 'upsert')->assertExactJson(['created' => 0, 'updated' => 1, 'skipped' => 1]);

    expect(inSchool($tenant, fn () => Student::query()->where('nis', '0072')->sole()->nisn))->toBe('8880072');
});
