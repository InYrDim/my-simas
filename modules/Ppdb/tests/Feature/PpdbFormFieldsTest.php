<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * Kolom formulir: the school decides, per period, which of the adjustable
 * fields the registration form asks (required, optional, not used). The
 * committee's form and the applicant's own form follow the period.
 */

/**
 * Every adjustable field in its usual state, with the given ones changed.
 *
 * @param  array<string, string>  $changes
 * @return array{fields: array<string, string>}
 */
function formSettings(array $changes = []): array
{
    return ['fields' => [
        'birth_place' => 'optional',
        'birth_date' => 'required',
        'nisn' => 'optional',
        'origin_school' => 'required',
        'address' => 'optional',
        'guardian_name' => 'required',
        'guardian_phone' => 'required',
        ...$changes,
    ]];
}

beforeEach(function () {
    // 10 February 2027, 10:00 in Jakarta: inside the default wave (1 Jan – 31 Mar).
    $this->travelTo('2027-02-10 03:00:00');
});

it('asks the usual form while a period has never been adjusted', function () {
    $tenant = ppdbTenant(slug: 'kolom-bawaan');
    [$period] = ppdbSetup($tenant);

    get(school($tenant->slug, '/ppdb/pengaturan'))->assertInertia(fn (Assert $page) => $page
        ->where('formFields', fn ($fields) => collect($fields)->pluck('value', 'key')->all() === [
            'birth_place' => 'optional',
            'birth_date' => 'required',
            'nisn' => 'optional',
            'origin_school' => 'required',
            'address' => 'optional',
            'guardian_name' => 'required',
            'guardian_phone' => 'required',
        ])
        ->where('fixedFields', ['Jalur', 'Nama lengkap', 'Jenis kelamin'])
    );

    expect(ppdbSchool($tenant, fn () => $period->fresh()->form_fields))->toBeNull();
});

it('saves the state of every field for the period', function () {
    $tenant = ppdbTenant(slug: 'kolom-simpan');
    [$period] = ppdbSetup($tenant);

    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}/formulir"), formSettings(['nisn' => 'off', 'origin_school' => 'optional']))
        ->assertRedirect()
        ->assertSessionHas('status');

    $stored = ppdbSchool($tenant, fn () => $period->fresh()->form_fields);

    expect($stored['nisn'])->toBe('off')
        ->and($stored['origin_school'])->toBe('optional')
        ->and($stored['birth_date'])->toBe('required');
});

it('refuses a state that does not exist and a field that is left out', function () {
    $tenant = ppdbTenant(slug: 'kolom-tolak');
    [$period] = ppdbSetup($tenant);
    $url = school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}/formulir");

    put($url, formSettings(['nisn' => 'hidden']))->assertSessionHasErrors('fields.nisn');

    $incomplete = formSettings();
    unset($incomplete['fields']['address']);

    put($url, $incomplete)->assertSessionHasErrors('fields.address');

    expect(ppdbSchool($tenant, fn () => $period->fresh()->form_fields))->toBeNull();
});

it('lets only those who manage the settings change the form', function () {
    $tenant = ppdbTenant(slug: 'kolom-izin', role: 'staf-tu');
    [$period] = ppdbSetup($tenant);

    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}/formulir"), formSettings(['nisn' => 'off']))->assertForbidden();

    expect(ppdbSchool($tenant, fn () => $period->fresh()->form_fields))->toBeNull();
});

it('does not change another school when the admin saves the form', function () {
    $tenant = ppdbTenant(slug: 'kolom-sekolah-a');
    [$period] = ppdbSetup($tenant);
    $other = ppdbTenant(slug: 'kolom-sekolah-b');
    [$otherPeriod] = ppdbSetup($other);

    // The signed-in user is the admin of the school made last; address the first school's period.
    put(school($other->slug, "/ppdb/pengaturan/periode/{$period->id}/formulir"), formSettings(['nisn' => 'off']))->assertNotFound();

    expect(ppdbSchool($tenant, fn () => $period->fresh()->form_fields))->toBeNull()
        ->and(ppdbSchool($other, fn () => $otherPeriod->fresh()->form_fields))->toBeNull();
});

it('lets the committee leave out a field the period does not ask, and keeps the rest required', function () {
    $tenant = ppdbTenant(slug: 'kolom-panitia');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbSchool($tenant, fn () => $period->update(['form_fields' => ['nisn' => 'off', 'origin_school' => 'off', 'guardian_phone' => 'optional']]));
    $url = school($tenant->slug, '/ppdb/pendaftar');
    $form = [
        'wave_id' => $wave->id,
        'path_id' => $path->id,
        'name' => 'Nadia Putri Anggraini',
        'gender' => 'P',
        'birth_date' => '2012-05-04',
        'guardian_name' => 'Budi Santoso',
    ];

    post($url, $form)->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect($applicant->nisn)->toBeNull()
        ->and($applicant->origin_school)->toBeNull()
        ->and($applicant->guardian_phone)->toBeNull();

    // Still required: the guardian's name.
    unset($form['guardian_name']);

    post($url, $form)->assertSessionHasErrors('guardian_name');
});

it('drops what is sent for a field that is off instead of saving it', function () {
    $tenant = ppdbTenant(slug: 'kolom-buang');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbSchool($tenant, fn () => $period->update(['form_fields' => ['nisn' => 'off']]));

    post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($wave, $path, ['nisn' => '0071234567']))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => Applicant::query()->sole()->nisn))->toBeNull();
});

it('requires a field the period made required and accepts it empty once optional', function () {
    $tenant = ppdbTenant(slug: 'kolom-wajib');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbSchool($tenant, fn () => $period->update(['form_fields' => ['nisn' => 'required', 'birth_date' => 'optional']]));
    $url = school($tenant->slug, '/ppdb/pendaftar');

    post($url, applicantForm($wave, $path, ['nisn' => null]))->assertSessionHasErrors('nisn');

    post($url, applicantForm($wave, $path, ['nisn' => '0071234567', 'birth_date' => null]))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => Applicant::query()->sole()->birth_date))->toBeNull();
});

it('keeps what applicants already gave when a field is switched off later', function () {
    $tenant = ppdbTenant(slug: 'kolom-data-lama');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, ['nisn' => '0071234567', 'origin_school' => 'SMPN 3 Bandung']);

    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$period->id}/formulir"), formSettings(['nisn' => 'off', 'origin_school' => 'off']))
        ->assertSessionHasNoErrors();
    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"), applicantForm($wave, $path, ['name' => 'Nama Baru']))->assertSessionHasNoErrors();

    $fresh = ppdbSchool($tenant, fn () => $applicant->fresh());

    expect($fresh->name)->toBe('Nama Baru')
        ->and($fresh->nisn)->toBe('0071234567')
        ->and($fresh->origin_school)->toBe('SMPN 3 Bandung');

    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('formFields.nisn', 'off')
        ->where('applicant.nisn', '0071234567')
    );
});

it('hides the origin school column of the list when the period does not ask for it', function () {
    $tenant = ppdbTenant(slug: 'kolom-daftar');
    [$period] = ppdbSetup($tenant);

    get(school($tenant->slug, '/ppdb/pendaftar'))->assertInertia(fn (Assert $page) => $page->where('showOrigin', true));

    ppdbSchool($tenant, fn () => $period->update(['form_fields' => ['origin_school' => 'off']]));

    get(school($tenant->slug, '/ppdb/pendaftar'))->assertInertia(fn (Assert $page) => $page->where('showOrigin', false));
});

it('gives a new period the form of the school\'s latest one', function () {
    $tenant = ppdbTenant(slug: 'kolom-salin');
    [$period] = ppdbSetup($tenant);
    ppdbSchool($tenant, fn () => $period->update(['form_fields' => ['nisn' => 'off', 'address' => 'required']]));

    post(school($tenant->slug, '/ppdb/pengaturan/periode'), ['name' => 'PPDB 2028/2029', 'entry_year' => 2028, 'status' => 'draft'])->assertSessionHasNoErrors();

    $new = ppdbSchool($tenant, fn () => AdmissionPeriod::query()->where('entry_year', 2028)->sole());

    expect($new->formFields()['nisn']->value)->toBe('off')
        ->and($new->formFields()['address']->value)->toBe('required');

    // Changing the new period leaves the old one as it was.
    put(school($tenant->slug, "/ppdb/pengaturan/periode/{$new->id}/formulir"), formSettings(['nisn' => 'optional']))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => $period->fresh()->form_fields['nisn']))->toBe('off');
});

it('starts a school\'s first period with the usual form', function () {
    $tenant = ppdbTenant(slug: 'kolom-pertama');

    post(school($tenant->slug, '/ppdb/pengaturan/periode'), ['name' => 'PPDB 2027/2028', 'entry_year' => 2027, 'status' => 'draft'])->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => AdmissionPeriod::query()->sole()->form_fields))->toBeNull();
});

it('shows the applicant\'s own form only the fields of the running period', function () {
    [$tenant, $period, , $path] = ppdbOpenSchool('kolom-calon');
    ppdbSchool($tenant, fn () => $period->update(['form_fields' => ['nisn' => 'off', 'origin_school' => 'optional']]));
    ppdbAccount($tenant);

    get('http://localhost/calon-siswa/formulir')->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Account/Form')
        ->where('formFields.nisn', 'off')
        ->where('formFields.origin_school', 'optional')
        ->where('formFields.birth_date', 'required')
    );

    post('http://localhost/calon-siswa/formulir', ownApplicationForm($path, ['nisn' => '0071234567', 'origin_school' => null]))
        ->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect($applicant->nisn)->toBeNull()
        ->and($applicant->origin_school)->toBeNull();
});

it('checks a correction against the form of the applicant\'s own period, not the running one', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('kolom-perbaikan');
    ppdbSchool($tenant, fn () => $period->update(['form_fields' => ['guardian_phone' => 'optional']]));
    $account = ppdbAccount($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, [
        'account_id' => $account->id,
        'status' => ApplicantStatus::Revision,
        'verification_note' => 'Lengkapi data.',
    ]);

    // The school opens a newer period that makes the phone required again.
    ppdbSchool($tenant, fn () => $period->update(['status' => PeriodStatus::Closed]));
    $newer = ppdbPeriod($tenant, ['entry_year' => 2028, 'status' => PeriodStatus::Active, 'form_fields' => ['guardian_phone' => 'required']]);
    ppdbWave($tenant, $newer);
    ppdbPath($tenant, $newer);

    put('http://localhost/calon-siswa/formulir', ownApplicationForm($path, ['guardian_phone' => null]))->assertSessionHasNoErrors();

    $fresh = ppdbSchool($tenant, fn () => $applicant->fresh());

    expect($fresh->guardian_phone)->toBeNull()
        ->and($fresh->status)->toBe(ApplicantStatus::Submitted);
});
