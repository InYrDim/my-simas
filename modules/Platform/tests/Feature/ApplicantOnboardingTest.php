<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Infrastructure\Mail\ApplicationApprovedMail;
use Modules\Platform\App\Infrastructure\Mail\ApplicationRejectedMail;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantApplicationFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Onboarding beyond the school form (Fase 4 Tahap 2): choosing a plan,
 * the trial that starts on it at approval, resubmitting a rejected
 * application, the decision mails, and that one applicant never sees or
 * touches another's application.
 */
function seedPlans(): void
{
    PlanFactory::new()->create(['key' => 'starter', 'name' => 'Starter', 'sort_order' => 1]);
    PlanFactory::new()->create(['key' => 'standard', 'name' => 'Standard', 'sort_order' => 2]);
    PlanFactory::new()->create(['key' => 'lama', 'name' => 'Lama', 'sort_order' => 3, 'is_active' => false]);
    PlanFactory::new()->create(['key' => 'arsip', 'name' => 'Arsip', 'sort_order' => 4])
        ->forceFill(['archived_at' => now()])->save();
}

function signedInApplicant(array $attributes = []): Applicant
{
    $applicant = ApplicantFactory::new()->create($attributes);

    actingAs($applicant, 'applicant');

    return $applicant;
}

function schoolPayload(array $overrides = []): array
{
    return [
        'school_name' => 'SMA Nusantara',
        'desired_slug' => 'sma-nusantara',
        'timezone' => 'Asia/Jakarta',
        'plan_key' => 'standard',
        ...$overrides,
    ];
}

// ---- Pilih paket

it('offers only the selectable plans, in the provider\'s order', function () {
    seedPlans();
    signedInApplicant();

    get('http://localhost/pemohon')->assertInertia(
        fn ($page) => $page
            ->where('plans', fn ($plans) => collect($plans)->pluck('key')->all() === ['starter', 'standard'])
            ->where('plans.0.name', 'Starter')
            ->has('plans.0.priceMonthly')
            ->where('trialDays', 14),
    );
});

it('refuses a plan that is inactive, archived or unknown', function (string $planKey) {
    seedPlans();
    signedInApplicant();

    post('http://localhost/pemohon/pengajuan', schoolPayload(['plan_key' => $planKey]))
        ->assertSessionHasErrors('application');

    expect(TenantApplication::query()->count())->toBe(0);
})->with(['lama', 'arsip', 'tidak-ada']);

// ---- Trial saat ACC

it('starts the trial on the chosen plan when the application is approved', function () {
    seedPlans();
    Mail::fake();
    Carbon::setTestNow('2026-10-01 09:00:00');
    $applicant = signedInApplicant(['name' => 'Budi Kepsek', 'email' => 'budi@nusantara.test']);
    post('http://localhost/pemohon/pengajuan', schoolPayload())->assertSessionHasNoErrors();

    $application = app(TenantApplications::class)->forApplicant($applicant->id);
    app(TenantApplications::class)->approve($application->id, ProviderUserFactory::new()->create()->id);

    $subscription = Subscription::query()->with('plan')->sole();

    expect($subscription->status)->toBe(SubscriptionStatus::Trial)
        ->and($subscription->plan->key)->toBe('standard')
        ->and($subscription->trial_ends_at->toDateString())->toBe('2026-10-15');

    Mail::assertQueued(
        ApplicationApprovedMail::class,
        fn (ApplicationApprovedMail $mail) => $mail->hasTo('budi@nusantara.test')
            && $mail->schoolName === 'SMA Nusantara'
            && $mail->schoolCode === $subscription->tenant_id
            && $mail->planName === 'Standard'
            && $mail->trialEndsOn === '2026-10-15'
            && str_ends_with($mail->loginUrl, '/pemohon/masuk')
            && ! str_contains($mail->loginUrl, 'console'),
    );
});

it('lets the provider correct the plan at approval', function () {
    seedPlans();
    $applicant = ApplicantFactory::new()->create();
    $application = TenantApplicationFactory::new()->forApplicant($applicant)->create(['plan_key' => 'standard']);
    $provider = ProviderUserFactory::new()->create();

    actingAs($provider, 'provider')
        ->post("http://console.localhost/applications/{$application->id}/approve", [
            'school_name' => $application->school_name,
            'desired_slug' => $application->desired_slug,
            'timezone' => 'Asia/Jakarta',
            'plan_key' => 'starter',
        ])
        ->assertSessionHasNoErrors();

    expect(Subscription::query()->with('plan')->sole()->plan->key)->toBe('starter')
        ->and($application->fresh()->plan_key)->toBe('starter');
});

it('refuses an unavailable plan picked by the provider and creates nothing', function () {
    seedPlans();
    $application = TenantApplicationFactory::new()->forApplicant(ApplicantFactory::new()->create())->create(['plan_key' => 'standard']);

    actingAs(ProviderUserFactory::new()->create(), 'provider')
        ->post("http://console.localhost/applications/{$application->id}/approve", [
            'school_name' => $application->school_name,
            'desired_slug' => $application->desired_slug,
            'timezone' => 'Asia/Jakarta',
            'plan_key' => 'arsip',
        ])
        ->assertSessionHasErrors('application');

    expect(DB::table('tenants')->count())->toBe(0)
        ->and($application->fresh()->status->value)->toBe('pending');
});

it('falls back to the default trial plan when the application carries none', function () {
    seedPlans();
    $application = TenantApplicationFactory::new()->create(['plan_key' => null]);

    app(TenantApplications::class)->approve($application->id, ProviderUserFactory::new()->create()->id);

    expect(Subscription::query()->with('plan')->sole()->plan->key)->toBe('starter');
});

it('still approves when the chosen plan was archived after submission', function () {
    seedPlans();
    $application = TenantApplicationFactory::new()->forApplicant(ApplicantFactory::new()->create())->create(['plan_key' => 'arsip']);

    $approved = app(TenantApplications::class)->approve($application->id, ProviderUserFactory::new()->create()->id);

    expect($approved->status)->toBe('approved')
        ->and(DB::table('tenants')->count())->toBe(1)
        ->and(Subscription::query()->count())->toBe(0);
});

it('shows the applicant and the plan on the provider review page', function () {
    seedPlans();
    $applicant = ApplicantFactory::new()->create();
    $application = TenantApplicationFactory::new()->forApplicant($applicant)->create(['plan_key' => 'standard']);

    actingAs(ProviderUserFactory::new()->create(), 'provider')
        ->get("http://console.localhost/applications/{$application->id}")
        ->assertInertia(
            fn ($page) => $page
                ->where('application.applicantId', $applicant->id)
                ->where('application.planKey', 'standard')
                ->where('plans', fn ($plans) => collect($plans)->pluck('key')->all() === ['starter', 'standard']),
        );
});

// ---- Ditolak → ajukan ulang

it('mails the applicant when the application is rejected', function () {
    seedPlans();
    Mail::fake();
    $applicant = ApplicantFactory::new()->create(['email' => 'budi@nusantara.test']);
    $application = TenantApplicationFactory::new()->forApplicant($applicant)->create(['school_name' => 'SMA Nusantara']);

    app(TenantApplications::class)->reject($application->id, 'Nama sekolah belum lengkap.', ProviderUserFactory::new()->create()->id);

    Mail::assertQueued(
        ApplicationRejectedMail::class,
        fn (ApplicationRejectedMail $mail) => $mail->hasTo('budi@nusantara.test')
            && $mail->note === 'Nama sekolah belum lengkap.'
            && str_ends_with($mail->onboardingUrl, '/pemohon'),
    );
});

it('sends no decision mail for an application without an applicant account', function () {
    seedPlans();
    Mail::fake();
    $provider = ProviderUserFactory::new()->create();

    app(TenantApplications::class)->reject(TenantApplicationFactory::new()->create()->id, null, $provider->id);
    app(TenantApplications::class)->approve(TenantApplicationFactory::new()->create()->id, $provider->id);

    Mail::assertNotQueued(ApplicationApprovedMail::class);
    Mail::assertNotQueued(ApplicationRejectedMail::class);
});

it('shows the rejection note and resubmits the same application', function () {
    seedPlans();
    $applicant = signedInApplicant();
    $application = TenantApplicationFactory::new()->forApplicant($applicant)
        ->rejected('Nama sekolah belum lengkap.')
        ->create(['desired_slug' => 'sma-ulang', 'plan_key' => 'starter']);

    get('http://localhost/pemohon')->assertInertia(
        fn ($page) => $page
            ->where('application.status', 'rejected')
            ->where('application.adminNote', 'Nama sekolah belum lengkap.'),
    );

    put('http://localhost/pemohon/pengajuan', schoolPayload([
        'school_name' => 'SMA Negeri 1 Ulang',
        'desired_slug' => 'sma-ulang',
    ]))
        ->assertRedirect(route('applicant.home'))
        ->assertSessionHasNoErrors();

    $row = $application->fresh();

    expect(TenantApplication::query()->count())->toBe(1)
        ->and($row->status->value)->toBe('pending')
        ->and($row->school_name)->toBe('SMA Negeri 1 Ulang')
        ->and($row->plan_key)->toBe('standard')
        ->and($row->decided_at)->toBeNull()
        ->and($row->admin_note)->toBe('Nama sekolah belum lengkap.');
});

it('refuses to resubmit an application that is not rejected, and to post a second one after rejection', function () {
    seedPlans();
    $applicant = signedInApplicant();
    $application = TenantApplicationFactory::new()->forApplicant($applicant)->create();

    put('http://localhost/pemohon/pengajuan', schoolPayload())->assertSessionHasErrors('application');

    $application->forceFill(['status' => 'rejected'])->save();

    post('http://localhost/pemohon/pengajuan', schoolPayload())->assertSessionHasErrors('application');

    expect(TenantApplication::query()->count())->toBe(1);
});

// ---- Isolasi antar pemohon

it('never shows or changes another applicant\'s application', function () {
    seedPlans();
    $other = ApplicantFactory::new()->create();
    $foreign = TenantApplicationFactory::new()->forApplicant($other)
        ->rejected()
        ->create(['school_name' => 'SMA Orang Lain', 'desired_slug' => 'sma-orang-lain']);

    signedInApplicant();

    get('http://localhost/pemohon')->assertInertia(fn ($page) => $page->where('application', null));

    // Resubmitting acts on the signed-in applicant only: they have no
    // application, so nothing happens to the other one.
    put('http://localhost/pemohon/pengajuan', schoolPayload(['desired_slug' => 'sma-orang-lain']))
        ->assertSessionHasErrors('application');

    expect($foreign->fresh()->status->value)->toBe('rejected')
        ->and($foreign->fresh()->school_name)->toBe('SMA Orang Lain');
});
