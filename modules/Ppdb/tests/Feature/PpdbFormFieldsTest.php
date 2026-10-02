<?php

namespace Modules\Ppdb\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Ppdb\App\Domain\Actions\SeedFormFields;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\FormField;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/Support/helpers.php';

/*
 * The registration form of a period: its built-in fields are rows, each
 * required, optional or archived, and the committee's form and the
 * applicant's own form follow the period.
 */

/**
 * Changes one built-in field of the period's form.
 *
 * @param  array<string, mixed>  $attributes
 */
function ppdbFormField(Tenant $tenant, AdmissionPeriod $period, string $key, array $attributes): void
{
    ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->where('key', $key)->sole()->update($attributes));
}

beforeEach(function () {
    // 10 February 2027, 10:00 in Jakarta: inside the default wave (1 Jan – 31 Mar).
    $this->travelTo('2027-02-10 03:00:00');
});

it('gives every new period the ten built-in fields in their usual order and state', function () {
    $tenant = ppdbTenant(slug: 'kolom-bawaan');
    $period = ppdbPeriod($tenant);

    $fields = ppdbSchool($tenant, fn () => $period->fields()->get());

    expect($fields->pluck('key')->all())->toBe([
        'path_id', 'name', 'gender', 'nisn', 'birth_place', 'birth_date', 'origin_school', 'address', 'guardian_name', 'guardian_phone',
    ])
        ->and($fields->every(fn (FormField $field): bool => $field->type === FieldType::Builtin && ! $field->isArchived()))->toBeTrue()
        ->and($fields->pluck('required', 'key')->all())->toBe([
            'path_id' => true, 'name' => true, 'gender' => true, 'nisn' => false, 'birth_place' => false,
            'birth_date' => true, 'origin_school' => true, 'address' => false, 'guardian_name' => true, 'guardian_phone' => true,
        ])
        ->and($fields->where('tenant_id', $tenant->id))->toHaveCount(10);
});

it('shows the committee the form of the running period', function () {
    $tenant = ppdbTenant(slug: 'kolom-tambah');
    [$period] = ppdbSetup($tenant);
    ppdbFormField($tenant, $period, 'nisn', ['archived_at' => now()]);
    ppdbFormField($tenant, $period, 'address', ['required' => true]);

    get(school($tenant->slug, '/ppdb/pendaftar/tambah'))->assertInertia(fn (Assert $page) => $page
        ->where('fields', fn ($fields) => collect($fields)->pluck('key')->all() === [
            'path_id', 'name', 'gender', 'nisn', 'birth_place', 'birth_date', 'origin_school', 'address', 'guardian_name', 'guardian_phone',
        ])
        ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'nisn')['archived'] === true)
        ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'address')['required'] === true)
        ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'birth_place')['required'] === false)
    );
});

it('lets the committee leave out a field the period does not ask, and keeps the rest required', function () {
    $tenant = ppdbTenant(slug: 'kolom-panitia');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbFormField($tenant, $period, 'nisn', ['archived_at' => now()]);
    ppdbFormField($tenant, $period, 'origin_school', ['archived_at' => now()]);
    ppdbFormField($tenant, $period, 'guardian_phone', ['required' => false]);
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

it('drops what is sent for a field that is archived instead of saving it', function () {
    $tenant = ppdbTenant(slug: 'kolom-buang');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbFormField($tenant, $period, 'nisn', ['archived_at' => now()]);

    post(school($tenant->slug, '/ppdb/pendaftar'), applicantForm($wave, $path, ['nisn' => '0071234567']))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => Applicant::query()->sole()->nisn))->toBeNull();
});

it('requires a field the period made required and accepts it empty once optional', function () {
    $tenant = ppdbTenant(slug: 'kolom-wajib');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbFormField($tenant, $period, 'nisn', ['required' => true]);
    ppdbFormField($tenant, $period, 'birth_date', ['required' => false]);
    $url = school($tenant->slug, '/ppdb/pendaftar');

    post($url, applicantForm($wave, $path, ['nisn' => null]))->assertSessionHasErrors('nisn');

    post($url, applicantForm($wave, $path, ['nisn' => '0071234567', 'birth_date' => null]))->assertSessionHasNoErrors();

    expect(ppdbSchool($tenant, fn () => Applicant::query()->sole()->birth_date))->toBeNull();
});

it('keeps the path, name and gender required whatever the form row says', function () {
    $tenant = ppdbTenant(slug: 'kolom-terkunci');
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbFormField($tenant, $period, 'gender', ['required' => false, 'archived_at' => now()]);
    $url = school($tenant->slug, '/ppdb/pendaftar');

    post($url, applicantForm($wave, $path, ['gender' => null]))->assertSessionHasErrors('gender');

    expect(ppdbSchool($tenant, fn () => Applicant::query()->count()))->toBe(0);
});

it('keeps what applicants already gave when a field is archived later', function () {
    $tenant = ppdbTenant(slug: 'kolom-data-lama');
    [$period, $wave, $path] = ppdbSetup($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, ['nisn' => '0071234567', 'origin_school' => 'SMPN 3 Bandung']);

    ppdbFormField($tenant, $period, 'nisn', ['archived_at' => now()]);
    ppdbFormField($tenant, $period, 'origin_school', ['archived_at' => now()]);
    put(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"), applicantForm($wave, $path, ['name' => 'Nama Baru']))->assertSessionHasNoErrors();

    $fresh = ppdbSchool($tenant, fn () => $applicant->fresh());

    expect($fresh->name)->toBe('Nama Baru')
        ->and($fresh->nisn)->toBe('0071234567')
        ->and($fresh->origin_school)->toBe('SMPN 3 Bandung');

    get(school($tenant->slug, "/ppdb/pendaftar/{$applicant->id}"))->assertInertia(fn (Assert $page) => $page
        ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'nisn')['archived'] === true)
        ->where('applicant.nisn', '0071234567')
    );
});

it('hides the origin school column of the list when the period does not ask for it', function () {
    $tenant = ppdbTenant(slug: 'kolom-daftar');
    [$period] = ppdbSetup($tenant);

    get(school($tenant->slug, '/ppdb/pendaftar'))->assertInertia(fn (Assert $page) => $page->where('showOrigin', true));

    ppdbFormField($tenant, $period, 'origin_school', ['archived_at' => now()]);

    get(school($tenant->slug, '/ppdb/pendaftar'))->assertInertia(fn (Assert $page) => $page->where('showOrigin', false));
});

it('gives a new period the form of the school\'s latest one', function () {
    $tenant = ppdbTenant(slug: 'kolom-salin');
    [$period] = ppdbSetup($tenant);
    ppdbFormField($tenant, $period, 'nisn', ['archived_at' => now()]);
    ppdbFormField($tenant, $period, 'address', ['required' => true]);
    ppdbSchool($tenant, fn () => FormField::factory()->create(['period_id' => $period->id, 'label' => 'Hobi']));
    ppdbSchool($tenant, fn () => FormField::factory()->archived()->create(['period_id' => $period->id, 'label' => 'Dulu']));

    post(school($tenant->slug, '/ppdb/pengaturan/periode'), ['name' => 'PPDB 2028/2029', 'entry_year' => 2028, 'status' => 'draft'])->assertSessionHasNoErrors();

    $new = ppdbSchool($tenant, fn () => AdmissionPeriod::query()->where('entry_year', 2028)->sole());
    $fields = ppdbSchool($tenant, fn () => $new->fields()->get());

    expect($fields->where('key', 'nisn')->sole()->isArchived())->toBeTrue()
        ->and($fields->where('key', 'address')->sole()->required)->toBeTrue()
        ->and($fields->pluck('label')->all())->toContain('Hobi')
        ->and($fields->pluck('label')->all())->not->toContain('Dulu')
        ->and($fields->where('period_id', $new->id))->toHaveCount($fields->count());

    // Changing the new period leaves the old one as it was.
    ppdbFormField($tenant, $new, 'nisn', ['archived_at' => null]);

    expect(ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->where('key', 'nisn')->sole()->isArchived()))->toBeTrue();
});

it('starts a school\'s first period with the usual form', function () {
    $tenant = ppdbTenant(slug: 'kolom-pertama');

    post(school($tenant->slug, '/ppdb/pengaturan/periode'), ['name' => 'PPDB 2027/2028', 'entry_year' => 2027, 'status' => 'draft'])->assertSessionHasNoErrors();

    $fields = ppdbSchool($tenant, fn () => AdmissionPeriod::query()->sole()->fields()->get());

    expect($fields)->toHaveCount(10)
        ->and($fields->contains(fn (FormField $field): bool => $field->isArchived()))->toBeFalse();
});

it('shows the applicant\'s own form only the fields of the running period', function () {
    [$tenant, $period, , $path] = ppdbOpenSchool('kolom-calon');
    ppdbFormField($tenant, $period, 'nisn', ['archived_at' => now()]);
    ppdbFormField($tenant, $period, 'origin_school', ['required' => false]);
    ppdbAccount($tenant);

    get('http://localhost/calon-siswa/formulir')->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Account/Form')
        ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'nisn')['archived'] === true)
        ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'origin_school')['required'] === false)
        ->where('fields', fn ($fields) => collect($fields)->firstWhere('key', 'birth_date')['required'] === true)
    );

    post('http://localhost/calon-siswa/formulir', ownApplicationForm($path, ['nisn' => '0071234567', 'origin_school' => null]))
        ->assertSessionHasNoErrors();

    $applicant = ppdbSchool($tenant, fn () => Applicant::query()->sole());

    expect($applicant->nisn)->toBeNull()
        ->and($applicant->origin_school)->toBeNull();
});

it('checks a correction against the form of the applicant\'s own period, not the running one', function () {
    [$tenant, $period, $wave, $path] = ppdbOpenSchool('kolom-perbaikan');
    ppdbFormField($tenant, $period, 'guardian_phone', ['required' => false]);
    $account = ppdbAccount($tenant);
    $applicant = ppdbApplicant($tenant, $period, $wave, $path, [
        'account_id' => $account->id,
        'status' => ApplicantStatus::Revision,
        'verification_note' => 'Lengkapi data.',
    ]);

    // The school opens a newer period whose form still requires the phone.
    ppdbSchool($tenant, fn () => $period->update(['status' => PeriodStatus::Closed]));
    $newer = ppdbPeriod($tenant, ['entry_year' => 2028, 'status' => PeriodStatus::Active]);
    ppdbWave($tenant, $newer);
    ppdbPath($tenant, $newer);

    put('http://localhost/calon-siswa/formulir', ownApplicationForm($path, ['guardian_phone' => null]))->assertSessionHasNoErrors();

    $fresh = ppdbSchool($tenant, fn () => $applicant->fresh());

    expect($fresh->guardian_phone)->toBeNull()
        ->and($fresh->status)->toBe(ApplicantStatus::Submitted);
});

it('seeds the form of a period that was inserted without one, the first time it is used', function () {
    $tenant = ppdbTenant(slug: 'kolom-kosong');
    $period = ppdbPeriod($tenant, ['status' => PeriodStatus::Active]);
    ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->delete());
    $count = fn (): int => ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->count());

    expect($count())->toBe(0);

    get(school($tenant->slug, '/ppdb/pendaftar/tambah'))->assertInertia(fn (Assert $page) => $page->has('fields', 10)->where('fields.0.key', 'path_id'));

    expect($count())->toBe(10);

    // Used again, nothing doubles.
    get(school($tenant->slug, '/ppdb/pendaftar/tambah'))->assertInertia(fn (Assert $page) => $page->has('fields', 10));
    get(school($tenant->slug, '/ppdb/formulir'))->assertInertia(fn (Assert $page) => $page->has('fields', 10));

    expect($count())->toBe(10);
});

it('seeds an empty period when the builder is opened or saved', function () {
    $tenant = ppdbTenant(slug: 'kolom-kosong-builder');
    $period = ppdbPeriod($tenant, ['status' => PeriodStatus::Active]);
    ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->delete());

    get(school($tenant->slug, '/ppdb/formulir'))->assertInertia(fn (Assert $page) => $page->has('fields', 10));

    ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->delete());

    $rows = ppdbSchool($tenant, function () use ($period): array {
        app(SeedFormFields::class)->ensure($period);

        return $period->fields()->get()->map(fn (FormField $field): array => [
            'id' => $field->id, 'type' => $field->type->value, 'label' => $field->label, 'required' => $field->required, 'archived' => false, 'options' => [], 'rules' => [],
        ])->all();
    });

    ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->delete());

    // The page was opened before the form existed, so its rows are stale: saving is refused, nothing breaks.
    put(school($tenant->slug, "/ppdb/formulir/{$period->id}"), ['fields' => $rows])->assertSessionHasErrors();

    expect(ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->count()))->toBe(10);
});

it('shows the applicant a seeded form for a period that had none', function () {
    [$tenant, $period] = ppdbOpenSchool('kolom-kosong-calon');
    ppdbSchool($tenant, fn () => FormField::query()->where('period_id', $period->id)->delete());
    ppdbAccount($tenant);

    get('http://localhost/calon-siswa/formulir')->assertInertia(fn (Assert $page) => $page->component('Ppdb/Account/Form')->has('fields', 10));
});
