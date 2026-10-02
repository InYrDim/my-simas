<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Ppdb\App\Domain\Enums\ApplicantSource;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\DocumentCheck;
use Modules\Ppdb\Tests\Feature\Support\NeverCompleteDocumentCheck;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Pendaftar: what the committee does with applicants — enter one by hand,
 * find them in the list, correct the data, verify, and cancel.
 */

/**
 * The form fields of an applicant, valid unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function applicantForm(AdmissionWave $wave, AdmissionPath $path, array $overrides = []): array
{
    return [
        'wave_id' => $wave->id,
        'path_id' => $path->id,
        'name' => 'Nadia Putri Anggraini',
        'gender' => 'P',
        'birth_place' => 'Bandung',
        'birth_date' => '2012-05-04',
        'nisn' => '0071234567',
        'origin_school' => 'SMPN 3 Bandung',
        'address' => 'Jl. Merdeka 10',
        'guardian_name' => 'Budi Santoso',
        'guardian_phone' => '081234567890',
        ...$overrides,
    ];
}

it('registers an applicant by hand with the first number, on the school day', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-baru');
    [, $wave, $path] = ppdbSetup($tenant);
    $this->travelTo('2027-01-12 03:00:00');

    $response = post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($wave, $path));

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    $response->assertRedirect("/ppdb/pendaftar/{$applicant->id}");
    expect($applicant->number)->toBe('PPDB-27-0001')
        ->and($applicant->name)->toBe('Nadia Putri Anggraini')
        ->and($applicant->birth_date)->toBe('2012-05-04')
        ->and($applicant->nisn)->toBe('0071234567')
        ->and($applicant->source)->toBe(ApplicantSource::Staff)
        ->and($applicant->status)->toBe(ApplicantStatus::Submitted)
        ->and($applicant->decision)->toBe(Decision::Pending)
        ->and($applicant->registered_on)->toBe('2027-01-12')
        ->and($applicant->account_id)->toBeNull()
        ->and($applicant->recorded_by)->not->toBeNull()
        ->and($applicant->tenant_id)->toBe($tenant->id);
});

it('numbers applicants in order and starts again in another school and year', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-nomor-a');
    [, $wave, $path] = ppdbSetup($tenant);
    $url = school($tenant->slug, '/ppdb/pendaftar');

    post($url, applicantForm($wave, $path, ['name' => 'Satu', 'nisn' => '001']));
    post($url, applicantForm($wave, $path, ['name' => 'Dua', 'nisn' => '002']));

    $other = ppdbTenant(slug: 'pendaftar-nomor-b');
    [, $otherWave, $otherPath] = ppdbSetup($other, entryYear: 2028);
    post(school($other->slug, '/ppdb/pendaftar'), applicantForm($otherWave, $otherPath));

    expect(ppdbSchool($tenant, fn () => Applicant::query()->orderBy('id')->pluck('number')->all()))->toBe(['PPDB-27-0001', 'PPDB-27-0002'])
        ->and(ppdbSchool($other, fn () => Applicant::query()->sole()->number))->toBe('PPDB-28-0001');
});

it('refuses an applicant with missing or impossible data and stores nothing', function (array $overrides, string $field) {
    $tenant = ppdbTenant(slug: 'pendaftar-validasi');
    [, $wave, $path] = ppdbSetup($tenant);
    $this->travelTo('2027-01-12 03:00:00');

    post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($wave, $path, $overrides))->assertSessionHasErrors($field);

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'no origin school' => [['origin_school' => ''], 'origin_school'],
    'no guardian' => [['guardian_name' => ''], 'guardian_name'],
    'no phone' => [['guardian_phone' => ''], 'guardian_phone'],
    'unknown gender' => [['gender' => 'X'], 'gender'],
    'birth date in words' => [['birth_date' => 'kemarin'], 'birth_date'],
    'born in the future' => [['birth_date' => '2030-01-01'], 'birth_date'],
]);

it('refuses a wave or a path that belongs to another period', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-periode-lain');
    [, $wave, $path] = ppdbSetup($tenant);
    $other = ppdbPeriod($tenant, ['name' => 'PPDB Lain', 'entry_year' => 2026, 'status' => PeriodStatus::Closed]);
    $foreignWave = ppdbWave($tenant, $other);
    $foreignPath = ppdbPath($tenant, $other, ['name' => 'Milik Lain']);
    $url = school($tenant->slug, '/ppdb/pendaftar');

    post($url, applicantForm($wave, $foreignPath))->assertSessionHasErrors('path_id');
    post($url, applicantForm($foreignWave, $path))->assertSessionHasErrors('wave_id');

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);
});

it('refuses an NISN already used in the period but accepts it in another period', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-nisn');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbApplicant($tenant, $period, $wave, $path, ['nisn' => '0079999999']);
    $url = school($tenant->slug, '/ppdb/pendaftar');

    post($url, applicantForm($wave, $path, ['nisn' => '0079999999']))->assertSessionHasErrors('nisn');
    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(1);

    // The same NISN in the next year's period is the same child applying again.
    ppdbSchool($tenant, fn () => $period->update(['status' => PeriodStatus::Closed]));
    [, $nextWave, $nextPath] = ppdbSetup($tenant, entryYear: 2028);

    post($url, applicantForm($nextWave, $nextPath, ['nisn' => '0079999999']))->assertSessionHasNoErrors();
    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(2);
});

it('registers nobody when no period is running', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-tanpa-periode');
    $closed = ppdbPeriod($tenant, ['status' => PeriodStatus::Closed]);
    $wave = ppdbWave($tenant, $closed);
    $path = ppdbPath($tenant, $closed);

    post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($wave, $path))->assertSessionHasErrors('period');

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);
});

it('lets the committee enter an applicant into any wave of the running period', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-gelombang-lain');
    [$period, , $path] = ppdbSetup($tenant);
    $later = ppdbWave($tenant, $period, ['name' => 'Gelombang 2', 'opens_on' => '2027-04-01', 'closes_on' => '2027-06-30']);
    $this->travelTo('2027-01-12 03:00:00');

    post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($later, $path))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => Applicant::query()->sole()->wave_id))->toBe($later->id);
});

it('shows the form to enter an applicant, or says there is no running period', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-form');

    get(school($tenant->slug, '/ppdb/pendaftar/tambah'))->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/ApplicantForm')
        ->where('period', null)
        ->where('waves', [])
    );

    ppdbSetup($tenant);

    get(school($tenant->slug, '/ppdb/pendaftar/tambah'))->assertInertia(fn (Assert $page) => $page
        ->where('period.name', 'PPDB 2027/2028')
        ->has('waves', 1)
        ->where('paths.0.label', 'Zonasi')
    );
});

it('lists applicants, searches by name or number, and filters by path and status', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-daftar');
    [$period, $wave, $zonasi] = ppdbSetup($tenant);
    $prestasi = ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1]);
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Anindya', 'number' => 'PPDB-27-0001']);
    ppdbApplicant($tenant, $period, $wave, $prestasi, ['name' => 'Bima', 'number' => 'PPDB-27-0002', 'status' => ApplicantStatus::Verified]);
    ppdbApplicant($tenant, $period, $wave, $zonasi, ['name' => 'Citra', 'number' => 'PPDB-27-0003', 'status' => ApplicantStatus::Revision]);

    $names = fn (string $query) => collect(
        get(school($tenant->slug, "/ppdb/pendaftar{$query}"))->assertOk()->viewData('page')['props']['applicants'],
    )->pluck('name')->all();

    expect($names(''))->toBe(['Anindya', 'Bima', 'Citra'])
        ->and($names('?cari=bim'))->toBe(['Bima'])
        ->and($names('?cari=0003'))->toBe(['Citra'])
        ->and($names("?jalur={$zonasi->id}"))->toBe(['Anindya', 'Citra'])
        ->and($names('?status=verified'))->toBe(['Bima'])
        ->and($names("?jalur={$zonasi->id}&status=revision"))->toBe(['Citra'])
        ->and($names('?cari=tidak-ada'))->toBe([]);
});

it('pages the list twenty at a time', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-halaman');
    [$period, $wave, $path] = ppdbSetup($tenant);

    foreach (range(1, 22) as $index) {
        ppdbApplicant($tenant, $period, $wave, $path, ['name' => "Pendaftar {$index}", 'number' => sprintf('PPDB-27-%04d', $index)]);
    }

    get(school($tenant->slug, '/ppdb/pendaftar'))->assertInertia(fn (Assert $page) => $page
        ->has('applicants', 20)
        ->where('pagination.total', 22)
        ->where('pagination.lastPage', 2)
    );

    get(school($tenant->slug, '/ppdb/pendaftar?page=2'))->assertInertia(fn (Assert $page) => $page
        ->has('applicants', 2)
        ->where('pagination.from', 21)
    );
});

it('shows a closed period\'s applicants when no period is running', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-periode-tutup');
    $period = ppdbPeriod($tenant, ['status' => PeriodStatus::Closed]);
    $wave = ppdbWave($tenant, $period);
    $path = ppdbPath($tenant, $period);
    ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Dari Periode Lama']);

    get(school($tenant->slug, '/ppdb/pendaftar'))->assertInertia(fn (Assert $page) => $page
        ->where('period.id', $period->id)
        ->where('applicants.0.name', 'Dari Periode Lama')
    );
});

it('shows one applicant with the choices to correct them', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-detail');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Rafi', 'nisn' => '0070000001', 'number' => 'PPDB-27-0001']);

    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/ApplicantShow')
        ->where('applicant.name', 'Rafi')
        ->where('applicant.number', 'PPDB-27-0001')
        ->where('applicant.pathName', 'Zonasi')
        ->where('applicant.waveName', 'Gelombang 1')
        ->where('applicant.status', 'submitted')
        ->where('can.manage', true)
        ->where('can.cancel', true)
        ->has('waves', 1)
        ->has('paths', 1)
    );
});

it('changes the data of an applicant', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-ubah');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, ['nisn' => '0070000002']);

    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"), applicantForm($wave, $path, [
        'name' => 'Nama Diperbaiki',
        'nisn' => '0070000002',
        'guardian_phone' => '085500000000',
    ]))->assertSessionHasNoErrors();

    $saved = ppdbSchool($tenant, fn () => $applicant->fresh());

    expect($saved->name)->toBe('Nama Diperbaiki')
        ->and($saved->guardian_phone)->toBe('085500000000')
        ->and($saved->number)->toBe($applicant->number)
        ->and($saved->status)->toBe(ApplicantStatus::Submitted);
});

it('does not let an applicant take the NISN of another applicant', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-ubah-nisn');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbApplicant($tenant, $period, $wave, $path, ['nisn' => '0070000003']);
    $second = ppdbApplicant($tenant, $period, $wave, $path, ['nisn' => '0070000004']);

    put(school($tenant->slug, "/ppdb/pendaftar/{$second->id}"), applicantForm($wave, $path, ['nisn' => '0070000003']))
        ->assertSessionHasErrors('nisn');

    expect(ppdbSchool($tenant, fn () => $second->fresh()->nisn))->toBe('0070000004');
});

it('locks the path once a decision is made and freezes an enrolled applicant', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-terkunci');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $other = ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1]);
    $decided = ppdbApplicant($tenant, $period, $wave, $path, ['decision' => Decision::Accepted, 'status' => ApplicantStatus::Verified, 'nisn' => '0070000005']);
    $enrolled = ppdbApplicant($tenant, $period, $wave, $path, ['decision' => Decision::Accepted, 'status' => ApplicantStatus::Verified, 'enrolled_at' => '2027-07-20 08:00:00', 'nisn' => '0070000006']);

    put(school($tenant->slug, "/ppdb/pendaftar/{$decided->id}"), applicantForm($wave, $other, ['nisn' => '0070000005']))
        ->assertSessionHasErrors('path_id');
    put(school($tenant->slug, "/ppdb/pendaftar/{$decided->id}"), applicantForm($wave, $path, ['name' => 'Boleh Diubah', 'nisn' => '0070000005']))
        ->assertSessionHasNoErrors();
    put(school($tenant->slug, "/ppdb/pendaftar/{$enrolled->id}"), applicantForm($wave, $path, ['name' => 'Tidak Boleh', 'nisn' => '0070000006']))
        ->assertSessionHasErrors('applicant');

    expect(ppdbSchool($tenant, fn () => $decided->fresh()->path_id))->toBe($path->id)
        ->and(ppdbSchool($tenant, fn () => $decided->fresh()->name))->toBe('Boleh Diubah')
        ->and(ppdbSchool($tenant, fn () => $enrolled->fresh()->name))->not->toBe('Tidak Boleh');
});

it('verifies an applicant, or asks for a correction with a note', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-verifikasi');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    $url = school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/verifikasi");

    put($url, ['status' => 'revision', 'verification_note' => '  Akta kelahiran belum terbaca.  '])->assertSessionHasNoErrors();
    expect(ppdbSchool($tenant, fn () => $applicant->fresh()))
        ->status->toBe(ApplicantStatus::Revision)
        ->verification_note->toBe('Akta kelahiran belum terbaca.');

    put($url, ['status' => 'verified', 'verification_note' => 'abaikan'])->assertSessionHasNoErrors();
    expect(ppdbSchool($tenant, fn () => $applicant->fresh()))
        ->status->toBe(ApplicantStatus::Verified)
        ->verification_note->toBeNull();
});

it('refuses a correction request without a note and an unknown status', function (array $data, string $field) {
    $tenant = ppdbTenant(slug: 'pendaftar-verifikasi-salah');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);

    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/verifikasi"), $data)->assertSessionHasErrors($field);

    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->status))->toBe(ApplicantStatus::Submitted);
})->with([
    'revision without note' => [['status' => 'revision', 'verification_note' => ''], 'verification_note'],
    'revision with blanks' => [['status' => 'revision', 'verification_note' => '   '], 'verification_note'],
    'unknown status' => [['status' => 'diterima'], 'status'],
]);

it('keeps a decided applicant verified and leaves an enrolled one alone', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-verifikasi-kunci');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $decided = ppdbApplicant($tenant, $period, $wave, $path, ['decision' => Decision::Waitlist, 'status' => ApplicantStatus::Verified]);
    $enrolled = ppdbApplicant($tenant, $period, $wave, $path, ['decision' => Decision::Accepted, 'status' => ApplicantStatus::Verified, 'enrolled_at' => '2027-07-20 08:00:00']);

    put(school($tenant->slug, "/ppdb/pendaftar/{$decided->id}/verifikasi"), ['status' => 'submitted'])->assertSessionHasErrors('status');
    put(school($tenant->slug, "/ppdb/pendaftar/{$enrolled->id}/verifikasi"), ['status' => 'verified'])->assertSessionHasErrors('status');

    expect(ppdbSchool($tenant, fn () => $decided->fresh()->status))->toBe(ApplicantStatus::Verified);
});

it('refuses to verify an applicant whose documents are incomplete', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-berkas');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);

    $this->app->bind(DocumentCheck::class, NeverCompleteDocumentCheck::class);

    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/verifikasi"), ['status' => 'verified'])->assertSessionHasErrors('status');

    expect(ppdbSchool($tenant, fn () => $applicant->fresh()->status))->toBe(ApplicantStatus::Submitted);
});

it('cancels a registration that has no decision yet, and keeps a decided one', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-batal');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $open = ppdbApplicant($tenant, $period, $wave, $path);
    $decided = ppdbApplicant($tenant, $period, $wave, $path, ['decision' => Decision::Rejected, 'status' => ApplicantStatus::Verified]);
    $enrolled = ppdbApplicant($tenant, $period, $wave, $path, ['decision' => Decision::Accepted, 'status' => ApplicantStatus::Verified, 'enrolled_at' => '2027-07-20 08:00:00']);

    delete(school($tenant->slug, "/ppdb/pendaftar/{$open->id}"))->assertRedirect('/ppdb/pendaftar');
    delete(school($tenant->slug, "/ppdb/pendaftar/{$decided->id}"))->assertSessionHasErrors('applicant');
    delete(school($tenant->slug, "/ppdb/pendaftar/{$enrolled->id}"))->assertSessionHasErrors('applicant');

    expect(ppdbSchool($tenant, fn () => Applicant::query()->orderBy('id')->pluck('id')->all()))->toBe([$decided->id, $enrolled->id]);
});

it('lets the committee staff work and keeps teachers and students out', function (string $role, bool $allowed) {
    $tenant = ppdbTenant(role: $role, slug: "pendaftar-izin-{$role}");
    [$period, $wave, $path] = ppdbSetup($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path);
    $ok = $allowed ? 200 : 403;
    $done = $allowed ? 302 : 403;

    get(school($tenant->slug, '/ppdb/pendaftar'))->assertStatus($ok);
    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertStatus($ok);
    get(school($tenant->slug, '/ppdb/pendaftar/tambah'))->assertStatus($ok);
    post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($wave, $path, ['nisn' => '0070000099']))->assertStatus($done);
    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"), applicantForm($wave, $path))->assertStatus($done);
    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}/verifikasi"), ['status' => 'verified'])->assertStatus($done);
    delete(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertStatus($done);
})->with([
    'admin' => ['admin-sekolah', true],
    'staf' => ['staf-tu', true],
    'guru' => ['guru', false],
    'siswa' => ['siswa', false],
]);

it('keeps another school out of the applicants', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-sekolah-a');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Milik A']);

    $other = ppdbTenant(slug: 'pendaftar-sekolah-b');
    [$otherPeriod, $otherWave, $otherPath] = ppdbSetup($other);
    $foreign = ppdbApplicant($other, $otherPeriod, $otherWave, $otherPath, ['name' => 'Milik B']);

    ppdbMember($tenant, 'admin-sekolah');

    get(school($tenant->slug, '/ppdb/pendaftar'))->assertInertia(fn (Assert $page) => $page
        ->where('applicants', fn ($rows) => collect($rows)->pluck('name')->all() === ['Milik A'])
    );

    get(school($tenant->slug, "/ppdb/pendaftar/{$foreign->id}"))->assertNotFound();
    put(school($tenant->slug, "/ppdb/pendaftar/{$foreign->id}"), applicantForm($wave, $path, ['name' => 'Dibajak']))->assertNotFound();
    put(school($tenant->slug, "/ppdb/pendaftar/{$foreign->id}/verifikasi"), ['status' => 'verified'])->assertNotFound();
    delete(school($tenant->slug, "/ppdb/pendaftar/{$foreign->id}"))->assertNotFound();

    expect(ppdbSchool($other, fn () => $foreign->fresh()->name))->toBe('Milik B')
        ->and(ppdbSchool($other, fn () => $foreign->fresh()->status))->toBe(ApplicantStatus::Submitted);
});

it('counts the funnel and the applicants of each wave on the overview', function () {
    $tenant = ppdbTenant(slug: 'ringkasan-corong');
    [$period, $first, $path] = ppdbSetup($tenant);
    $second = ppdbWave($tenant, $period, ['name' => 'Gelombang 2', 'opens_on' => '2027-04-01', 'closes_on' => '2027-06-30']);
    ppdbApplicant($tenant, $period, $first, $path);
    ppdbApplicant($tenant, $period, $first, $path, ['status' => ApplicantStatus::Verified]);
    ppdbApplicant($tenant, $period, $second, $path, ['decision' => Decision::Accepted, 'status' => ApplicantStatus::Verified]);
    ppdbApplicant($tenant, $period, $second, $path, ['decision' => Decision::Accepted, 'status' => ApplicantStatus::Verified, 'enrolled_at' => '2027-07-20 08:00:00']);

    get(school($tenant->slug, '/ppdb'))->assertInertia(fn (Assert $page) => $page
        ->where('funnel', fn ($funnel) => collect($funnel)->pluck('count', 'key')->all() === [
            'submitted' => 4,
            'verified' => 3,
            'accepted' => 2,
            'registered' => 1,
        ])
        ->where('waves', fn ($waves) => collect($waves)->pluck('applicants', 'name')->all() === ['Gelombang 1' => 2, 'Gelombang 2' => 2])
    );
});

it('does not delete a wave or a path that applicants depend on', function () {
    $tenant = ppdbTenant(slug: 'atur-hapus-dipakai');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $extraPath = ppdbPath($tenant, $period, ['name' => 'Prestasi', 'sort_order' => 1]);
    $emptyWave = ppdbWave($tenant, $period, ['name' => 'Kosong', 'opens_on' => '2027-04-01', 'closes_on' => '2027-04-30']);
    ppdbApplicant($tenant, $period, $wave, $path);

    delete(school($tenant->slug, "/ppdb/pengaturan/gelombang/{$wave->id}"))->assertSessionHasErrors('wave');
    delete(school($tenant->slug, "/ppdb/pengaturan/jalur/{$path->id}"))->assertSessionHasErrors('path');
    delete(school($tenant->slug, "/ppdb/pengaturan/gelombang/{$emptyWave->id}"))->assertSessionHasNoErrors();
    delete(school($tenant->slug, "/ppdb/pengaturan/jalur/{$extraPath->id}"))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => AdmissionWave::query()->pluck('id')->all()))->toBe([$wave->id])
        ->and(ppdbSchool($tenant, fn () => AdmissionPath::query()->pluck('id')->all()))->toBe([$path->id]);
});

it('keeps at least one path in a period', function () {
    $tenant = ppdbTenant(slug: 'atur-jalur-terakhir');
    $period = ppdbPeriod($tenant);
    $only = ppdbPath($tenant, $period);

    delete(school($tenant->slug, "/ppdb/pengaturan/jalur/{$only->id}"))->assertSessionHasErrors('path');

    expect(ppdbSchool($tenant, fn () => AdmissionPath::query()->count()))->toBe(1);
});

it('keeps another school\'s waves and paths out of reach of the delete actions', function () {
    $tenant = ppdbTenant(slug: 'atur-hapus-a');
    $other = ppdbTenant(slug: 'atur-hapus-b');
    [, $foreignWave, $foreignPath] = ppdbSetup($other);
    ppdbPath($other, AdmissionPeriod::query()->withoutGlobalScopes()->findOrFail($foreignPath->period_id), ['name' => 'Cadangan', 'sort_order' => 1]);
    ppdbMember($tenant, 'admin-sekolah');

    delete(school($tenant->slug, "/ppdb/pengaturan/gelombang/{$foreignWave->id}"))->assertNotFound();
    delete(school($tenant->slug, "/ppdb/pengaturan/jalur/{$foreignPath->id}"))->assertNotFound();

    expect(ppdbSchool($other, fn () => AdmissionWave::query()->count()))->toBe(1)
        ->and(ppdbSchool($other, fn () => AdmissionPath::query()->count()))->toBe(2);
});

it('records who entered the applicant', function () {
    $tenant = ppdbTenant(slug: 'pendaftar-pencatat');
    [, $wave, $path] = ppdbSetup($tenant);
    $user = ppdbMember($tenant, 'staf-tu');

    post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($wave, $path))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => Applicant::query()->sole()->recorded_by))->toBe($user->id);
});
