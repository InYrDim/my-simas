<?php

use Illuminate\Support\Facades\Event;
use Modules\Platform\App\Contracts\DTOs\ApplicationData;
use Modules\Platform\App\Contracts\Events\TenantApproved;
use Modules\Platform\App\Contracts\Events\TenantCreated;
use Modules\Platform\App\Contracts\Exceptions\ApplicationNotPendingException;
use Modules\Platform\App\Contracts\Exceptions\InvalidApplicationException;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Contracts\TenantRoles;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantApplicationFactory;
use Modules\Platform\Database\Factories\TenantFactory;

/**
 * Stage 5 (Fase 2): school applications — submit validation, approval
 * transaction (tenant + onboarding flags + TenantApproved), rejection,
 * idempotency guards. Identity's first-admin listener does not exist
 * yet (Stage 8), so nothing listens to TenantApproved here.
 */
function applications(): TenantApplications
{
    return app(TenantApplications::class);
}

function providerDecider(): ProviderUser
{
    /** @var ProviderUser $provider */
    $provider = ProviderUserFactory::new()->create();

    return $provider;
}

/**
 * Submit through the contract as the applicant named in the payload
 * (the account is created on first use, so two submissions with the
 * same email come from the SAME applicant).
 *
 * @param  array<string, mixed>  $payload
 */
function submitApplication(array $payload): ApplicationData
{
    $applicant = Applicant::query()->firstOrCreate(
        ['email' => mb_strtolower((string) $payload['applicant_email'])],
        ['name' => $payload['applicant_name'], 'password' => 'password'],
    );

    return applications()->submit($applicant->id, $payload);
}

function validApplicationPayload(): array
{
    return [
        'school_name' => 'SMA Negeri Uji Coba',
        'desired_slug' => 'sman-uji',
        'timezone' => 'Asia/Jakarta',
        'applicant_name' => 'Budi Santoso',
        'applicant_email' => 'budi@sman-uji.sch.id',
        'applicant_message' => 'Mohon segera di-ACC, tahun ajaran sudah mulai.',
    ];
}

it('accepts a valid application as pending', function () {
    submitApplication(validApplicationPayload());

    $pending = applications()->pending();

    expect($pending)->toHaveCount(1)
        ->and($pending[0])->toBeInstanceOf(ApplicationData::class)
        ->and($pending[0]->schoolName)->toBe('SMA Negeri Uji Coba')
        ->and($pending[0]->desiredSlug)->toBe('sman-uji')
        ->and($pending[0]->status)->toBe('pending')
        ->and($pending[0]->decidedAt)->toBeNull();
});

it('rejects submissions violating slug rules', function (string $slug, ?string $seed) {
    if ($seed === 'tenant') {
        TenantFactory::new()->create(['slug' => 'taken-slug']);
    } elseif ($seed === 'pending') {
        TenantApplicationFactory::new()->create(['desired_slug' => 'taken-slug']);
    }

    submitApplication([...validApplicationPayload(), 'desired_slug' => $slug]);
})
    ->throws(InvalidApplicationException::class)
    ->with([
        'reserved slug' => ['www', null],
        'invalid shape' => ['Bad_Slug!', null],
        'taken by existing tenant' => ['taken-slug', 'tenant'],
        'taken by pending application' => ['taken-slug', 'pending'],
    ]);

it('rejects a second pending application for the same applicant email', function () {
    submitApplication(validApplicationPayload());

    submitApplication([
        ...validApplicationPayload(),
        'desired_slug' => 'other-school',
        'applicant_email' => 'budi@sman-uji.sch.id',
    ]);
})->throws(InvalidApplicationException::class, 'pending application');

it('sends a rejected application back to review on the same row', function () {
    $decider = providerDecider();
    $submitted = submitApplication(validApplicationPayload());
    applications()->reject($submitted->id, 'Belum lengkap', $decider->id);

    $resubmitted = applications()->resubmit($submitted->applicantId, [
        ...validApplicationPayload(),
        'school_name' => 'SMA Negeri Uji Coba Lengkap',
    ]);

    expect($resubmitted->id)->toBe($submitted->id)
        ->and($resubmitted->status)->toBe('pending')
        ->and($resubmitted->schoolName)->toBe('SMA Negeri Uji Coba Lengkap')
        ->and($resubmitted->decidedAt)->toBeNull()
        ->and($resubmitted->decidedBy)->toBeNull()
        // The provider's note stays for the next review.
        ->and($resubmitted->adminNote)->toBe('Belum lengkap')
        ->and(applications()->pending())->toHaveCount(1)
        ->and(DB::table('tenant_applications')->count())->toBe(1);
});

it('refuses to submit a new application after a rejection, and to resubmit one that is not rejected', function () {
    $decider = providerDecider();
    $submitted = submitApplication(validApplicationPayload());

    expect(fn () => applications()->resubmit($submitted->applicantId, validApplicationPayload()))
        ->toThrow(ApplicationNotPendingException::class);

    applications()->reject($submitted->id, null, $decider->id);

    expect(fn () => submitApplication(validApplicationPayload()))
        ->toThrow(InvalidApplicationException::class, 'resubmit');
});

it('returns the application of an applicant, or null when there is none', function () {
    $submitted = submitApplication(validApplicationPayload());
    $other = Applicant::query()->create(['name' => 'Lain', 'email' => 'lain@sman-uji.sch.id', 'password' => 'password']);

    expect(applications()->forApplicant($submitted->applicantId)?->id)->toBe($submitted->id)
        ->and(applications()->forApplicant($other->id))->toBeNull();
});

it('rejects a submission from an unknown applicant', function () {
    applications()->submit(999_999, validApplicationPayload());
})->throws(InvalidApplicationException::class, 'does not exist');

it('rejects invalid timezone on submit', function () {
    submitApplication([...validApplicationPayload(), 'timezone' => 'Not/AZone']);
})->throws(InvalidApplicationException::class);

it('approves transactionally: tenant + default roles + onboarding flags + decision stamped', function () {
    $decider = providerDecider();
    submitApplication(validApplicationPayload());
    $id = applications()->pending()[0]->id;

    $data = applications()->approve($id, $decider->id);

    $tenant = Tenant::query()->where('slug', 'sman-uji')->firstOrFail();

    expect($data->status)->toBe('approved')
        ->and($data->decidedBy)->toBe($decider->id)
        ->and($data->decidedAt)->not->toBeNull()
        ->and($tenant->name)->toBe('SMA Negeri Uji Coba')
        ->and($tenant->status->value)->toBe('active');

    // Default school roles seeded via TenantCreated (Stage 2 listener).
    $roleNames = app(TenantRoles::class)->names($tenant->id);
    sort($roleNames);

    expect($roleNames)->toBe(['admin-sekolah', 'guru', 'staf-tu']);

    // Onboarding flags from config (flagged modules only).
    expect(TenantModules::class)
        ->toBeString()
        ->and(app(TenantModules::class)->isEnabled('identity', $tenant->id))->toBeTrue();

    // The application row carries the final decision.
    $row = DB::table('tenant_applications')->find($id);
    expect($row)->not->toBeNull();
    expect($row->status)->toBe('approved')
        ->and($row->decided_by)->toBe($decider->id)
        ->and($row->decided_at)->not->toBeNull();
});

it('applies provider corrections at approval: final tenant data is the corrected payload', function () {
    $decider = providerDecider();
    TenantApplicationFactory::new()->create([
        'school_name' => 'SMA Typo',
        'desired_slug' => 'sma-typo',
        'applicant_email' => 'kepsek@typo.sch.id',
    ]);
    $id = applications()->pending()[0]->id;

    applications()->approve($id, $decider->id, [
        'school_name' => 'SMA Dikoreksi',
        'desired_slug' => 'sma-benar',
        'timezone' => 'Asia/Makassar',
    ]);

    $tenant = Tenant::query()->where('slug', 'sma-benar')->firstOrFail();

    expect($tenant->name)->toBe('SMA Dikoreksi')
        ->and($tenant->timezone)->toBe('Asia/Makassar');

    // The application row mirrors the corrections too.
    $row = DB::table('tenant_applications')->find($id);
    expect($row)->not->toBeNull();
    expect($row->desired_slug)->toBe('sma-benar')
        ->and($row->school_name)->toBe('SMA Dikoreksi');
});

it('re-validates corrections at approval: cannot approve onto a taken slug', function () {
    $decider = providerDecider();
    TenantFactory::new()->create(['slug' => 'sma-bentrok']);
    TenantApplicationFactory::new()->create(['desired_slug' => 'sma-akan']);
    $id = applications()->pending()[0]->id;

    applications()->approve($id, $decider->id, ['desired_slug' => 'sma-bentrok']);
})->throws(InvalidApplicationException::class);

it('fires TenantApproved inside approval with the right payload', function () {
    Event::fake([TenantApproved::class]);

    $decider = providerDecider();
    submitApplication(validApplicationPayload());
    $id = applications()->pending()[0]->id;

    applications()->approve($id, $decider->id);

    Event::assertDispatched(TenantApproved::class, function (TenantApproved $event): bool {
        $tenant = Tenant::query()->where('slug', 'sman-uji')->firstOrFail();

        return $event->tenantId === $tenant->id
            && $event->applicantName === 'Budi Santoso'
            && $event->applicantEmail === 'budi@sman-uji.sch.id';
    });
});

it('does not fire TenantApproved when tenant creation or listeners fail (transaction rolls back)', function () {
    $decider = providerDecider();
    submitApplication(validApplicationPayload());
    $id = applications()->pending()[0]->id;

    // TenantApproved listener that explodes: approval must abort AND
    // roll back the tenant, the module flags, and the decision stamp.
    Event::listen(TenantApproved::class, function (): never {
        throw new RuntimeException('Identity provisioning failed');
    });

    try {
        applications()->approve($id, $decider->id);
        $this->fail('Approval should have failed.');
    } catch (RuntimeException) {
        // expected
    }

    expect(Tenant::query()->where('slug', 'sman-uji')->exists())->toBeFalse()
        ->and(DB::table('tenant_applications')->find($id)->status)->toBe('pending')
        ->and(DB::table('tenant_modules')->count())->toBe(0);
});

it('rejects a pending application without side effects', function () {
    Event::fake([TenantApproved::class, TenantCreated::class]);

    $decider = providerDecider();
    TenantApplicationFactory::new()->create(['desired_slug' => 'ditolak']);
    $id = applications()->pending()[0]->id;

    $data = applications()->reject($id, 'Slug tidak pantas', $decider->id);

    expect($data->status)->toBe('rejected')
        ->and($data->adminNote)->toBe('Slug tidak pantas')
        ->and(Tenant::query()->where('slug', 'ditolak')->exists())->toBeFalse()
        ->and(DB::table('tenant_modules')->count())->toBe(0);

    Event::assertNotDispatched(TenantApproved::class);
    Event::assertNotDispatched(TenantCreated::class);
});

it('guards idempotency: deciding twice throws', function () {
    $decider = providerDecider();
    TenantApplicationFactory::new()->create();
    $id = applications()->pending()[0]->id;

    applications()->approve($id, $decider->id);
    applications()->approve($id, $decider->id);
})->throws(ApplicationNotPendingException::class);

it('guards rejection after approval', function () {
    $decider = providerDecider();
    TenantApplicationFactory::new()->create();
    $id = applications()->pending()[0]->id;

    applications()->approve($id, $decider->id);
    applications()->reject($id, null, $decider->id);
})->throws(ApplicationNotPendingException::class);

it('refuses to decide with an unknown provider user (fail closed)', function () {
    TenantApplicationFactory::new()->create();
    $id = applications()->pending()[0]->id;

    applications()->approve($id, 999999);
})->throws(InvalidArgumentException::class);
