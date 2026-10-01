<?php

use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\TenantApplicationFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * School application from the applicant's onboarding page (Fase 4): the
 * school form that used to be public at /daftar-sekolah now sits behind
 * a verified applicant account. Domain rules (slug reserved/taken/
 * duplicate, one application per applicant) live in the
 * TenantApplications contract and are proven end-to-end here over HTTP.
 * A successful submit only creates a pending row — no tenant, no school
 * login. Plan choice, resubmission and decision mails are covered in
 * ApplicantOnboardingTest.
 */
function onboardingApplicant(array $attributes = []): Applicant
{
    PlanFactory::new()->create(['key' => 'starter', 'name' => 'Starter', 'sort_order' => 1]);

    $applicant = ApplicantFactory::new()->create($attributes);

    actingAs($applicant, 'applicant');

    return $applicant;
}

it('shows the school form to a verified applicant without an application', function () {
    $applicant = onboardingApplicant(['name' => 'Budi Kepsek', 'email' => 'budi@nusantara.test']);

    get('http://localhost/pemohon')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Platform/Applicant/Onboarding')
                ->where('applicant.email', $applicant->email)
                ->where('application', null)
                ->has('timezones', 3)
                ->has('plans', 1),
        );
});

it('serves onboarding even when a school is remembered in the session', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);
    onboardingApplicant();

    get(school('sekolah-a', '/pemohon'))->assertOk();
});

it('stores a valid submission as a pending application under the applicant identity', function () {
    $applicant = onboardingApplicant(['name' => 'Budi Kepsek', 'email' => 'budi@nusantara.test']);

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => 'SMA Nusantara',
        'desired_slug' => 'sma-nusantara',
        'timezone' => 'Asia/Jakarta',
        'plan_key' => 'starter',
        'applicant_message' => 'Siap mulai semester ini.',
        // Identity comes from the account, never from the form.
        'applicant_email' => 'orang-lain@nusantara.test',
        'applicant_id' => 999,
    ])
        ->assertRedirect(route('applicant.home'))
        ->assertSessionHas('status');

    $row = DB::table('tenant_applications')
        ->where('desired_slug', 'sma-nusantara')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('pending')
        ->and($row->applicant_id)->toBe($applicant->id)
        ->and($row->applicant_name)->toBe('Budi Kepsek')
        ->and($row->applicant_email)->toBe('budi@nusantara.test')
        ->and($row->plan_key)->toBe('starter')
        ->and($row->submitted_at)->not->toBeNull()
        // A submission creates NOTHING but the row.
        ->and(DB::table('tenants')->where('slug', 'sma-nusantara')->exists())->toBeFalse();
});

it('shows the status instead of the form once an application is pending', function () {
    $applicant = onboardingApplicant();
    TenantApplicationFactory::new()->forApplicant($applicant)->create([
        'school_name' => 'SMA Nunggu',
        'plan_key' => 'starter',
    ]);

    get('http://localhost/pemohon')->assertInertia(
        fn ($page) => $page
            ->where('application.status', 'pending')
            ->where('application.schoolName', 'SMA Nunggu')
            ->where('application.planKey', 'starter'),
    );
});

it('refuses a second application while one is pending', function () {
    $applicant = onboardingApplicant();
    TenantApplicationFactory::new()->forApplicant($applicant)->create();

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => 'SMA Dobel',
        'desired_slug' => 'sma-dobel',
        'plan_key' => 'starter',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');

    expect(DB::table('tenant_applications')->where('desired_slug', 'sma-dobel')->exists())->toBeFalse();
});

it('rejects a slug colliding with an existing tenant', function () {
    TenantFactory::new()->create(['slug' => 'sma-bentrok']);
    onboardingApplicant();

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => 'SMA Bentrok Baru',
        'desired_slug' => 'sma-bentrok',
        'plan_key' => 'starter',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');

    expect(DB::table('tenant_applications')->count())->toBe(0);
});

it('rejects a slug that is already pending approval', function () {
    TenantApplicationFactory::new()->create(['desired_slug' => 'sma-nunggu']);
    onboardingApplicant();

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => 'SMA Nunggu Juga',
        'desired_slug' => 'sma-nunggu',
        'plan_key' => 'starter',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');
});

it('validates required fields', function () {
    onboardingApplicant();

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => '',
        'desired_slug' => '',
        'plan_key' => '',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['school_name', 'desired_slug', 'plan_key']);

    expect(DB::table('tenant_applications')->count())->toBe(0);
});

it('keeps guests and unverified applicants out of onboarding', function () {
    get('http://localhost/pemohon')->assertRedirect(route('applicant.login'));
    post('http://localhost/pemohon/pengajuan', ['school_name' => 'X', 'desired_slug' => 'x'])
        ->assertRedirect(route('applicant.login'));

    actingAs(ApplicantFactory::new()->unverified()->create(), 'applicant');

    get('http://localhost/pemohon')->assertRedirect(route('applicant.verify.notice'));
    post('http://localhost/pemohon/pengajuan', ['school_name' => 'X', 'desired_slug' => 'x'])
        ->assertRedirect(route('applicant.verify.notice'));

    expect(DB::table('tenant_applications')->count())->toBe(0);
});
