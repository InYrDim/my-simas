<?php

namespace Modules\Ppdb\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Pest\Laravel\get;

require_once __DIR__.'/Support/helpers.php';

/*
 * PPDB on Statistik & Laporan: two reports and the admissions figures,
 * registered through Core's contracts. The academic year runs from 13 July
 * 2026 to 26 June 2027; applicants count for the year they registered in.
 */

/**
 * A school with the academic year 2026/2027, and — unless told otherwise —
 * a running period with two paths.
 *
 * @return array{0: Tenant, 1: AdmissionPeriod, 2: AdmissionWave, 3: AdmissionPath, 4: AdmissionPath}
 */
function admissionsSchool(string $slug, string $role = 'admin-sekolah', bool $enabled = true): array
{
    $tenant = ppdbTenant($enabled, $role, $slug);
    ppdbSchool($tenant, fn () => AcademicYear::factory()->active()->create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-26']));

    $period = ppdbPeriod($tenant, ['status' => PeriodStatus::Active]);
    $wave = ppdbWave($tenant, $period);
    $zonasi = ppdbPath($tenant, $period, ['name' => 'Zonasi', 'sort_order' => 0, 'quota' => 5]);
    $prestasi = ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1, 'quota' => 5]);

    return [$tenant, $period, $wave, $zonasi, $prestasi];
}

/**
 * The rows of a downloaded report, without the `sep=` line.
 *
 * @param  TestResponse<StreamedResponse>  $response
 * @return list<list<string|null>>
 */
function admissionsCsv(TestResponse $response): array
{
    $lines = explode("\r\n", trim($response->streamedContent()));

    expect(array_shift($lines))->toBe('sep=;');

    return array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), $lines);
}

it('takes the place of the announced PPDB reports', function () {
    [$tenant] = admissionsSchool('laporan-ppdb-katalog');

    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => collect($groups)->firstWhere('title', 'Penerimaan (PPDB)')['reports'] === [
            ['key' => 'ppdb-applicants', 'name' => 'Daftar Pendaftar PPDB', 'description' => 'Pendaftar menurut jalur dan status verifikasi.', 'available' => true],
            ['key' => 'ppdb-result', 'name' => 'Hasil Seleksi PPDB', 'description' => 'Peringkat, keputusan, dan daftar cadangan.', 'available' => true],
        ])
    );
});

it('downloads the applicants of the academic year with their path, wave and status', function () {
    [$tenant, $period, $wave, $zonasi, $prestasi] = admissionsSchool('laporan-ppdb-daftar');
    ppdbApplicant($tenant, $period, $wave, $prestasi, ['name' => 'Rafi Maulana', 'number' => 'PPDB-27-0002', 'gender' => 'L', 'origin_school' => 'SMP Al-Azhar', 'registered_on' => '2027-02-03', 'status' => ApplicantStatus::Verified]);
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Nadia Putri', 'number' => 'PPDB-27-0001', 'gender' => 'P', 'origin_school' => 'SMPN 3 Bandung', 'registered_on' => '2027-01-12', 'source' => ApplicantSource::Online]);
    // Registered before the academic year began: not in this year's report.
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Tahun Lalu', 'number' => 'PPDB-26-0001', 'registered_on' => '2026-06-01']);

    $response = get(school($tenant->slug, '/statistik-laporan/laporan/ppdb-applicants/unduh'))->assertOk()->assertDownload('ppdb-applicants-2026-2027.csv');

    expect(admissionsCsv($response))->toBe([
        ['No. Daftar', 'Nama', 'Jenis Kelamin', 'Asal Sekolah', 'Jalur', 'Gelombang', 'Tanggal Daftar', 'Status Verifikasi', 'Sumber'],
        ['PPDB-27-0001', 'Nadia Putri', 'Perempuan', 'SMPN 3 Bandung', 'Zonasi', 'Gelombang 1', '12 Januari 2027', 'Menunggu verifikasi', 'Online'],
        ['PPDB-27-0002', 'Rafi Maulana', 'Laki-laki', 'SMP Al-Azhar', 'Prestasi', 'Gelombang 1', '3 Februari 2027', 'Terverifikasi', 'Panitia'],
    ]);
});

it('adds the custom fields of the form as columns of the applicant list', function () {
    [$tenant, $period, $wave, $zonasi] = admissionsSchool('laporan-ppdb-kustom');
    $hobby = customField($tenant, $period, FieldType::Checkboxes, ['Membaca', 'Olahraga'], attributes: ['label' => 'Hobi']);
    $diploma = customField($tenant, $period, FieldType::File, attributes: ['label' => 'Ijazah']);
    $unused = customField($tenant, $period, FieldType::Text, attributes: ['label' => 'Tanpa Jawaban', 'archived_at' => now()]);
    customField($tenant, $period, FieldType::Section, attributes: ['label' => 'Bagian']);
    $nadia = ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Nadia', 'number' => 'PPDB-27-0001', 'registered_on' => '2027-01-12']);
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Rafi', 'number' => 'PPDB-27-0002', 'registered_on' => '2027-01-13']);
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $nadia->id, 'field_id' => $hobby->id, 'value' => '["Membaca","Olahraga"]']));
    ppdbSchool($tenant, fn () => ApplicantAnswer::factory()->create(['applicant_id' => $nadia->id, 'field_id' => $diploma->id, 'value' => '{"path":"x.pdf","name":"ijazah.pdf","size":1,"mime":"application/pdf"}']));

    $response = get(school($tenant->slug, '/statistik-laporan/laporan/ppdb-applicants/unduh'))->assertOk();
    $rows = admissionsCsv($response);

    expect($unused->label)->toBe('Tanpa Jawaban')
        ->and(array_slice($rows[0], 9))->toBe(['Hobi', 'Ijazah'])
        ->and(array_slice($rows[1], 9))->toBe(['Membaca, Olahraga', 'Ada'])
        ->and(array_slice($rows[2], 9))->toBe(['', '']);
});

it('downloads the selection result path by path, highest score first, verified applicants only', function () {
    [$tenant, $period, $wave, $zonasi, $prestasi] = admissionsSchool('laporan-ppdb-hasil');
    $verified = ['status' => ApplicantStatus::Verified, 'registered_on' => '2027-01-12'];
    ppdbApplicant($tenant, $period, $wave, $prestasi, [...$verified, 'name' => 'Anindya', 'number' => 'PPDB-27-0001', 'score' => '94.50', 'decision' => Decision::Accepted, 'enrolled_at' => '2027-07-20 08:00:00']);
    ppdbApplicant($tenant, $period, $wave, $zonasi, [...$verified, 'name' => 'Tiara', 'number' => 'PPDB-27-0002', 'score' => '86.10', 'decision' => Decision::Waitlist]);
    ppdbApplicant($tenant, $period, $wave, $zonasi, [...$verified, 'name' => 'Nadia', 'number' => 'PPDB-27-0003', 'score' => '88.40', 'decision' => Decision::Accepted]);
    ppdbApplicant($tenant, $period, $wave, $zonasi, [...$verified, 'name' => 'Belum Dinilai', 'number' => 'PPDB-27-0004']);
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Belum Terverifikasi', 'number' => 'PPDB-27-0005', 'registered_on' => '2027-01-12']);

    $response = get(school($tenant->slug, '/statistik-laporan/laporan/ppdb-result/unduh'))->assertOk();

    expect(admissionsCsv($response))->toBe([
        ['Jalur', 'Peringkat', 'No. Daftar', 'Nama', 'Nilai', 'Keputusan', 'Daftar Ulang'],
        ['Zonasi', '1', 'PPDB-27-0003', 'Nadia', '88.40', 'Diterima', 'Belum'],
        ['Zonasi', '2', 'PPDB-27-0002', 'Tiara', '86.10', 'Cadangan', 'Belum'],
        ['Zonasi', '', 'PPDB-27-0004', 'Belum Dinilai', '', 'Belum diputuskan', 'Belum'],
        ['Prestasi', '1', 'PPDB-27-0001', 'Anindya', '94.50', 'Diterima', 'Sudah'],
    ]);
});

it('prints the report for the academic year', function () {
    [$tenant, $period, $wave, $zonasi] = admissionsSchool('laporan-ppdb-cetak');
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Nadia Putri', 'number' => 'PPDB-27-0001', 'registered_on' => '2027-01-12']);

    get(school($tenant->slug, '/statistik-laporan/laporan/ppdb-applicants/cetak'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Insight/ReportPrint')
        ->where('title', 'Daftar Pendaftar PPDB')
        ->where('rows.0.1', 'Nadia Putri')
    );
});

it('keeps the reports for holders of ppdb.view', function (string $role, bool $allowed) {
    [$tenant] = admissionsSchool("laporan-ppdb-izin-{$role}", $role);

    get(school($tenant->slug, '/statistik-laporan/laporan/ppdb-applicants/unduh'))->assertStatus($allowed ? 200 : 403);
    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => (collect($groups)->firstWhere('title', 'Penerimaan (PPDB)') !== null) === $allowed)
    );
})->with([
    'admin' => ['admin-sekolah', true],
    'staf' => ['staf-tu', true],
    'guru' => ['guru', false],
]);

it('offers neither reports nor figures to a school without the module', function () {
    [$tenant] = admissionsSchool('laporan-ppdb-mati', enabled: false);

    get(school($tenant->slug, '/statistik-laporan/laporan/ppdb-applicants/unduh'))->assertNotFound();
    get(school($tenant->slug, '/statistik-laporan/laporan'))->assertInertia(fn (Assert $page) => $page
        ->where('groups', fn ($groups) => collect($groups)->firstWhere('title', 'Penerimaan (PPDB)') === null)
    );
    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'ppdb-applicants-total') === null)
    );
});

it('shows the number of applicants and how they split over the paths', function () {
    [$tenant, $period, $wave, $zonasi, $prestasi] = admissionsSchool('statistik-ppdb');
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['number' => 'PPDB-27-0001']);
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['number' => 'PPDB-27-0002']);
    ppdbApplicant($tenant, $period, $wave, $prestasi, ['number' => 'PPDB-27-0003']);

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'ppdb-applicants-total') === [
            'key' => 'ppdb-applicants-total', 'label' => 'Pendaftar PPDB', 'value' => 3, 'hint' => 'PPDB 2027/2028', 'available' => true,
        ])
        ->where('panels', function ($panels) {
            $panel = collect($panels)->firstWhere('key', 'ppdb-by-path');

            return $panel['available'] === true
                && $panel['kind'] === 'bars'
                && $panel['points'] === [['label' => 'Zonasi', 'value' => 2], ['label' => 'Prestasi', 'value' => 1]];
        })
    );
});

it('says there is nothing yet when the school has no PPDB period', function () {
    $tenant = ppdbTenant(slug: 'statistik-ppdb-kosong');
    ppdbSchool($tenant, fn () => AcademicYear::factory()->active()->create(['name' => '2026/2027', 'start_date' => '2026-07-13', 'end_date' => '2027-06-26']));

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'ppdb-applicants-total')['value'] === '—')
        ->where('panels', fn ($panels) => collect($panels)->where('key', 'ppdb-by-path')->where('available', true)->isEmpty())
    );
});

it('counts the newest period when none is running', function () {
    [$tenant, $period, $wave, $zonasi] = admissionsSchool('statistik-ppdb-tutup');
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['number' => 'PPDB-27-0001']);
    ppdbSchool($tenant, fn () => $period->update(['status' => PeriodStatus::Closed]));

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'ppdb-applicants-total')['value'] === 1)
    );
});

it('never reports another school', function () {
    [$tenant, $period, $wave, $zonasi] = admissionsSchool('laporan-ppdb-a');
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Milik A', 'number' => 'PPDB-27-0001', 'registered_on' => '2027-01-12']);

    [$other, $otherPeriod, $otherWave, $otherZonasi] = admissionsSchool('laporan-ppdb-b');
    ppdbApplicant($other, $otherPeriod, $otherWave, $otherZonasi, ['name' => 'Milik B', 'number' => 'PPDB-27-0001', 'registered_on' => '2027-01-12']);
    ppdbMember($tenant, 'admin-sekolah');

    $rows = admissionsCsv(get(school($tenant->slug, '/statistik-laporan/laporan/ppdb-applicants/unduh'))->assertOk());

    expect(array_column($rows, 1))->toBe(['Nama', 'Milik A']);

    get(school($tenant->slug, '/statistik-laporan/statistik'))->assertInertia(fn (Assert $page) => $page
        ->where('figures', fn ($figures) => collect($figures)->firstWhere('key', 'ppdb-applicants-total')['value'] === 1)
    );
});
