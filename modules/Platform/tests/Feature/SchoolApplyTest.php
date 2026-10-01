<?php

use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\TenantApplicationStatus;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\TenantApplicationFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * School application from the applicant's onboarding page (Fase 4
 * Tahap 1): the school form that used to be public at /daftar-sekolah
 * now sits behind a verified applicant account. Domain rules (slug
 * reserved/taken/duplicate, one pending application per email) still
 * live in the TenantApplications contract and are proven end-to-end here
 * over HTTP. A successful submit only creates a pending row — no tenant,
 * no school login.
 */
function onboardingApplicant(array $attributes = []): Applicant
{
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
                ->has('timezones', 3),
        );
});

it('serves onboarding even when a school is remembered in the session', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);
    onboardingApplicant();

    get(school('sekolah-a', '/pemohon'))->assertOk();
});

it('stores a valid submission as a pending application under the applicant identity', function () {
    onboardingApplicant(['name' => 'Budi Kepsek', 'email' => 'budi@nusantara.test']);

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => 'SMA Nusantara',
        'desired_slug' => 'sma-nusantara',
        'timezone' => 'Asia/Jakarta',
        'applicant_message' => 'Siap mulai semester ini.',
        // Identity comes from the account, never from the form.
        'applicant_email' => 'orang-lain@nusantara.test',
    ])
        ->assertRedirect(route('applicant.home'))
        ->assertSessionHas('status');

    $row = DB::table('tenant_applications')
        ->where('desired_slug', 'sma-nusantara')
        ->first();

    expect($row)->not->toBeNull()
        ->and($row->status)->toBe('pending')
        ->and($row->applicant_name)->toBe('Budi Kepsek')
        ->and($row->applicant_email)->toBe('budi@nusantara.test')
        // A submission creates NOTHING but the row.
        ->and(DB::table('tenants')->where('slug', 'sma-nusantara')->exists())->toBeFalse();
});

it('shows the status instead of the form once an application is pending', function () {
    onboardingApplicant(['email' => 'kepsek@nunggu.test']);
    TenantApplicationFactory::new()->create([
        'applicant_email' => 'kepsek@nunggu.test',
        'school_name' => 'SMA Nunggu',
    ]);

    get('http://localhost/pemohon')->assertInertia(
        fn ($page) => $page
            ->where('application.status', 'pending')
            ->where('application.schoolName', 'SMA Nunggu'),
    );
});

it('refuses a second application while one is pending', function () {
    onboardingApplicant(['email' => 'kepsek@dobel.test']);
    TenantApplicationFactory::new()->create(['applicant_email' => 'kepsek@dobel.test']);

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => 'SMA Dobel',
        'desired_slug' => 'sma-dobel',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');

    expect(DB::table('tenant_applications')->where('desired_slug', 'sma-dobel')->exists())->toBeFalse();
});

it('lets a rejected applicant see the note and apply again', function () {
    onboardingApplicant(['email' => 'kepsek@ulang.test']);
    TenantApplicationFactory::new()->create([
        'applicant_email' => 'kepsek@ulang.test',
        'desired_slug' => 'sma-ulang',
        'status' => TenantApplicationStatus::Rejected,
        'admin_note' => 'Nama sekolah belum lengkap.',
    ]);

    get('http://localhost/pemohon')->assertInertia(
        fn ($page) => $page
            ->where('application.status', 'rejected')
            ->where('application.adminNote', 'Nama sekolah belum lengkap.'),
    );

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => 'SMA Negeri 1 Ulang',
        'desired_slug' => 'sma-ulang',
    ])
        ->assertRedirect(route('applicant.home'))
        ->assertSessionHasNoErrors();

    expect(DB::table('tenant_applications')->where('applicant_email', 'kepsek@ulang.test')->where('status', 'pending')->count())->toBe(1);
});

it('rejects a slug colliding with an existing tenant', function () {
    TenantFactory::new()->create(['slug' => 'sma-bentrok']);
    onboardingApplicant();

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => 'SMA Bentrok Baru',
        'desired_slug' => 'sma-bentrok',
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
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('application');
});

it('validates required fields', function () {
    onboardingApplicant();

    post('http://localhost/pemohon/pengajuan', [
        'school_name' => '',
        'desired_slug' => '',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['school_name', 'desired_slug']);

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
