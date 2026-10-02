<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Infrastructure\Mail\ApplicantVerifyMail;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\TenantApplicationFactory;

require_once __DIR__.'/Support/onboarding.php';

/**
 * The school onboarding journey in a real browser (Fase 4): what the
 * applicant and the provider actually click through, with the database
 * checked behind every step that matters.
 */
it('takes an applicant from registration to their approved school', function () {
    Mail::fake();
    seedOnboardingPlans();

    // --- Register
    $page = visit('/daftar-sekolah');

    $page->fill('name', 'Budi Kepsek')
        ->fill('email', 'budi@nusantara.test')
        ->fill('password', 'rahasia-sekali')
        ->fill('password_confirmation', 'rahasia-sekali')
        ->press('Buat akun')
        ->assertPathIs('/pemohon/verifikasi')
        ->assertSee('Verifikasi email Anda');

    $applicant = Applicant::query()->sole();
    expect($applicant->hasVerifiedEmail())->toBeFalse();

    // --- Verify the email through the mailed link
    $page->navigate(mailedPath(ApplicantVerifyMail::class, 'verifyUrl'));

    expect($applicant->fresh()->hasVerifiedEmail())->toBeTrue();

    // The guard still holds the applicant it loaded before verification
    // (one PHP process serves every request here); a real server reads
    // the applicant again on the next request.
    refreshSignedInUsers();

    $page->navigate('/pemohon')
        ->assertPathIs('/pemohon')
        ->assertSee('Daftarkan sekolah');

    // --- Fill in the school, choose a plan, submit
    $page->fill('school_name', 'SMA Nusantara')
        ->fill('desired_slug', 'sma-nusantara')
        ->click('Standard')
        ->press('Ajukan sekolah')
        ->assertSee('Menunggu persetujuan')
        ->assertSee('SMA Nusantara')
        ->assertNoJavaScriptErrors();

    $application = TenantApplication::query()->sole();

    expect($application->status->value)->toBe('pending')
        ->and($application->applicant_id)->toBe($applicant->id)
        ->and($application->plan_key)->toBe('standard');

    // --- The provider approves on the console host
    $console = providerSignsIn();

    $console->navigate('/applications')
        ->click('SMA Nusantara')
        ->assertSee('budi@nusantara.test')
        ->press('Setujui & buat sekolah')
        ->assertSee('Aplikasi disetujui')
        ->assertNoJavaScriptErrors();

    $applicant->refresh();
    $subscription = Subscription::query()->with('plan')->sole();

    expect($application->fresh()->status->value)->toBe('approved')
        ->and($applicant->tenant_id)->not->toBeNull()
        // The password moved to the school admin account.
        ->and($applicant->password)->toBeNull()
        ->and($subscription->tenant_id)->toBe($applicant->tenant_id)
        ->and($subscription->plan->key)->toBe('standard');

    // --- The applicant signs out, signs in again, and lands in the school
    onCentralHost();

    $page->navigate('/pemohon')
        ->assertSee('Disetujui')
        ->press('Keluar dan masuk ke sekolah')
        ->assertPathIs('/pemohon/masuk')
        ->fill('email', 'budi@nusantara.test')
        ->fill('password', 'rahasia-sekali')
        ->press('Masuk')
        ->assertPathIs('/beranda')
        ->assertSee('SMA Nusantara')
        ->assertNoJavaScriptErrors();

    $admin = app(TenantContext::class)->run(
        (string) $applicant->tenant_id,
        fn () => User::query()->where('email', 'budi@nusantara.test')->sole(),
    );

    expect(Hash::check('rahasia-sekali', $admin->password))->toBeTrue();
});

it('shows the rejection note and lets the applicant correct and resubmit', function () {
    Mail::fake();
    seedOnboardingPlans();

    $applicant = ApplicantFactory::new()->create(['email' => 'budi@nusantara.test', 'password' => 'rahasia-sekali']);
    $application = TenantApplicationFactory::new()->forApplicant($applicant)->create([
        'school_name' => 'SMA Nusantra',
        'desired_slug' => 'sma-nusantara',
        'plan_key' => 'starter',
    ]);

    // --- The provider rejects with a note
    $console = providerSignsIn();

    $console->navigate('/applications')
        ->click('SMA Nusantra')
        ->click('Tolak pengajuan...')
        ->fill('reject-note', 'Nama sekolah salah ketik.')
        ->click('internal:role=button[name="Ya, tolak"i]')
        ->assertSee('Aplikasi ditolak');

    expect($application->fresh()->status->value)->toBe('rejected');

    // --- The applicant sees the note with the form filled in, and resubmits
    $page = applicantSignsIn('budi@nusantara.test', 'rahasia-sekali');

    $page->assertPathIs('/pemohon')
        ->assertSee('Nama sekolah salah ketik.')
        ->assertValue('school_name', 'SMA Nusantra')
        ->fill('school_name', 'SMA Nusantara')
        ->press('Ajukan ulang')
        ->assertSee('Menunggu persetujuan')
        ->assertSee('SMA Nusantara')
        ->assertNoJavaScriptErrors();

    $application->refresh();

    expect(TenantApplication::query()->count())->toBe(1)
        ->and($application->status->value)->toBe('pending')
        ->and($application->school_name)->toBe('SMA Nusantara')
        ->and($application->plan_key)->toBe('starter')
        ->and($application->admin_note)->toBe('Nama sekolah salah ketik.');
});

it('asks for a plan before the school can be submitted', function () {
    seedOnboardingPlans();
    ApplicantFactory::new()->create(['email' => 'budi@nusantara.test', 'password' => 'rahasia-sekali']);

    $page = applicantSignsIn('budi@nusantara.test', 'rahasia-sekali');

    $page->assertPathIs('/pemohon')
        ->fill('school_name', 'SMA Nusantara')
        ->fill('desired_slug', 'sma-nusantara')
        ->press('Ajukan sekolah')
        ->assertSee('Pilih salah satu paket.')
        ->assertPathIs('/pemohon');

    expect(TenantApplication::query()->count())->toBe(0);
});
