<?php

namespace Modules\Ppdb\Tests\Feature;

use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\Database\Factories\PpdbAccountFactory;

use function Pest\Laravel\actingAs;

/*
 * Helpers shared by the Ppdb feature tests: a school with the module on
 * and a signed-in member of it.
 */

/**
 * A school (module on unless told otherwise) with a signed-in user of the
 * given role.
 */
function ppdbTenant(bool $enabled = true, string $role = 'admin-sekolah', ?string $slug = null): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug ?? ($enabled ? 'ppdb-on' : 'ppdb-off')]);

    if ($enabled) {
        app(ModuleFlagManager::class)->enable($tenant->id, 'ppdb');
    }

    ppdbMember($tenant, $role);

    return $tenant;
}

/**
 * Sign in as a new user of the school holding the role (null = no role).
 */
function ppdbMember(Tenant $tenant, ?string $role): User
{
    $user = UserFactory::new()->forTenant($tenant->id)->create();

    if ($role !== null) {
        ppdbSchool($tenant, fn () => $user->assignTenantRole($role));
    }

    actingAs($user);

    return $user;
}

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function ppdbSchool(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant->id, $callback);
}

/**
 * A period of the school (draft unless `status` says otherwise).
 *
 * @param  array<string, mixed>  $attributes
 */
function ppdbPeriod(Tenant $tenant, array $attributes = []): AdmissionPeriod
{
    return ppdbSchool($tenant, fn (): AdmissionPeriod => AdmissionPeriod::factory()->create($attributes));
}

/**
 * A wave of the period.
 *
 * @param  array<string, mixed>  $attributes
 */
function ppdbWave(Tenant $tenant, AdmissionPeriod $period, array $attributes = []): AdmissionWave
{
    return ppdbSchool($tenant, fn (): AdmissionWave => AdmissionWave::factory()->create([...$attributes, 'period_id' => $period->id]));
}

/**
 * A path of the period.
 *
 * @param  array<string, mixed>  $attributes
 */
function ppdbPath(Tenant $tenant, AdmissionPeriod $period, array $attributes = []): AdmissionPath
{
    return ppdbSchool($tenant, fn (): AdmissionPath => AdmissionPath::factory()->create([...$attributes, 'period_id' => $period->id]));
}

/**
 * A running period (2027) with one wave in the first quarter and a Zonasi
 * path — what an applicant needs to be registered.
 *
 * @return array{0: AdmissionPeriod, 1: AdmissionWave, 2: AdmissionPath}
 */
function ppdbSetup(Tenant $tenant, int $entryYear = 2027): array
{
    $period = ppdbPeriod($tenant, ['entry_year' => $entryYear, 'status' => PeriodStatus::Active]);

    return [$period, ppdbWave($tenant, $period), ppdbPath($tenant, $period)];
}

/**
 * An applicant of the period; `number` is made up from the id unless given.
 *
 * @param  array<string, mixed>  $attributes
 */
function ppdbApplicant(Tenant $tenant, AdmissionPeriod $period, AdmissionWave $wave, AdmissionPath $path, array $attributes = []): Applicant
{
    static $sequence = 0;

    $number = sprintf('PPDB-T-%04d', ++$sequence);

    return ppdbSchool($tenant, fn (): Applicant => Applicant::factory()->create([
        'number' => $number,
        ...$attributes,
        'period_id' => $period->id,
        'wave_id' => $wave->id,
        'path_id' => $path->id,
    ]));
}

/**
 * A school with PPDB on and a running period: one wave from 1 January to
 * 31 March 2027 and a Zonasi path. No user is signed in.
 *
 * @return array{0: Tenant, 1: AdmissionPeriod, 2: AdmissionWave, 3: AdmissionPath}
 */
function ppdbOpenSchool(string $slug): array
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);
    app(ModuleFlagManager::class)->enable($tenant->id, 'ppdb');

    $period = ppdbPeriod($tenant, ['status' => PeriodStatus::Active]);

    return [$tenant, $period, ppdbWave($tenant, $period), ppdbPath($tenant, $period)];
}

/**
 * An applicant's account, signed in on the `ppdb` guard; joined to the
 * school when one is given.
 *
 * @param  array<string, mixed>  $attributes
 */
function ppdbAccount(?Tenant $tenant = null, array $attributes = []): PpdbAccount
{
    $factory = PpdbAccountFactory::new();

    if ($tenant !== null) {
        $factory = $factory->joined($tenant->id);
    }

    $account = $factory->create($attributes);

    actingAs($account, 'ppdb');

    return $account;
}

/**
 * The form fields of an applicant as the committee sends them, valid unless
 * overridden.
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

/**
 * What an applicant sends from their own form, valid unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function ownApplicationForm(AdmissionPath $path, array $overrides = []): array
{
    return [
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

/**
 * A custom field of the period.
 *
 * @param  list<string>  $options
 * @param  array<string, mixed>  $rules
 * @param  array<string, mixed>  $attributes
 */
function customField(Tenant $tenant, AdmissionPeriod $period, FieldType $type, array $options = [], array $rules = [], array $attributes = []): FormField
{
    static $order = 100;

    return ppdbSchool($tenant, fn () => FormField::factory()->create([
        'period_id' => $period->id,
        'type' => $type,
        'label' => 'Pertanyaan '.$type->value,
        'options' => $options === [] ? null : $options,
        'rules' => $rules === [] ? null : $rules,
        'sort_order' => ++$order,
        ...$attributes,
    ]));
}

/**
 * The stored answer of the applicant to the field, or null.
 */
function storedAnswer(Tenant $tenant, Applicant $applicant, FormField $field): ?string
{
    return ppdbSchool($tenant, fn () => ApplicantAnswer::query()
        ->where('applicant_id', $applicant->id)
        ->where('field_id', $field->id)
        ->value('value'));
}

/**
 * A running period with a Zonasi path of the given quota and verified
 * applicants named Anindya (92.00), Bima (88.40) and Citra (no score).
 *
 * @return array{0: Tenant, 1: AdmissionPeriod, 2: AdmissionWave, 3: AdmissionPath, 4: array<string, Applicant>}
 */
function selectionSchool(string $slug, int $quota = 2, string $role = 'admin-sekolah'): array
{
    $tenant = ppdbTenant(role: $role, slug: $slug);
    [$period, $wave, $path] = ppdbSetup($tenant);
    ppdbSchool($tenant, fn () => $path->update(['quota' => $quota]));

    $applicants = [
        'Anindya' => ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Anindya', 'number' => 'PPDB-27-0001', 'status' => ApplicantStatus::Verified, 'score' => '92.00']),
        'Bima' => ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Bima', 'number' => 'PPDB-27-0002', 'status' => ApplicantStatus::Verified, 'score' => '88.40']),
        'Citra' => ppdbApplicant($tenant, $period, $wave, $path, ['name' => 'Citra', 'number' => 'PPDB-27-0003', 'status' => ApplicantStatus::Verified]),
    ];

    return [$tenant, $period, $wave, $path, $applicants];
}

/**
 * The body of a selection save for the path.
 *
 * @param  array<int, array{0: Applicant, 1: string|null, 2: string}>  $rows  applicant, score, decision
 * @return array<string, mixed>
 */
function selectionBody(int $pathId, array $rows): array
{
    return [
        'path_id' => $pathId,
        'rows' => array_map(fn (array $row): array => ['applicant_id' => $row[0]->id, 'score' => $row[1], 'decision' => $row[2]], $rows),
    ];
}
