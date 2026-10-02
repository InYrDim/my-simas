<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Mail\ApplicantInvitationMail;
use Modules\Platform\App\Infrastructure\Mail\ApplicantPasswordResetMail;
use Modules\Platform\Database\Factories\ApplicantFactory;

require_once __DIR__.'/Support/onboarding.php';

/**
 * Getting into an applicant account through a mailed link (Fase 4
 * Tahap 4): the provider's invitation, and a forgotten password.
 */
it('lets an invited applicant set a password and start onboarding', function () {
    Mail::fake();
    seedOnboardingPlans();

    // --- The provider invites from the console
    $console = providerSignsIn();

    $console->navigate('/applicants')
        ->fill('invite-name', 'Budi Kepsek')
        ->fill('invite-email', 'budi@nusantara.test')
        ->press('Kirim undangan')
        ->assertSee('Undangan dikirim ke budi@nusantara.test')
        ->assertSee('belum aktivasi')
        ->assertNoJavaScriptErrors();

    $applicant = Applicant::query()->sole();
    expect($applicant->password)->toBeNull();

    // --- The applicant opens the link and sets a password
    onCentralHost();

    $page = visit(mailedPath(ApplicantInvitationMail::class, 'invitationUrl'));

    $page->assertSee('Aktifkan akun Anda')
        ->fill('password', 'rahasia-sekali')
        ->fill('password_confirmation', 'rahasia-sekali')
        ->press('Simpan kata sandi')
        ->assertPathIs('/pemohon')
        ->assertSee('Daftarkan sekolah')
        ->assertSee('Starter')
        ->assertNoJavaScriptErrors();

    $applicant->refresh();

    expect(Hash::check('rahasia-sekali', $applicant->password))->toBeTrue()
        ->and($applicant->hasVerifiedEmail())->toBeTrue();
});

it('lets an applicant reset a forgotten password and sign in with the new one', function () {
    Mail::fake();
    seedOnboardingPlans();
    $applicant = ApplicantFactory::new()->create(['email' => 'budi@nusantara.test', 'password' => 'sandi-lama-sekali']);

    // --- Ask for the reset link from the login page
    $page = visit('/pemohon/masuk');

    $page->click('Lupa kata sandi?')
        ->assertPathIs('/pemohon/lupa-sandi')
        ->fill('email', 'budi@nusantara.test')
        ->press('Kirim petunjuk')
        ->assertSee('Jika email terdaftar, petunjuk atur ulang kata sandi telah dikirim.');

    // --- Open the link and choose a new password
    $page->navigate(mailedPath(ApplicantPasswordResetMail::class, 'resetUrl'))
        ->assertSee('Buat kata sandi baru')
        ->fill('password', 'sandi-baru-sekali')
        ->fill('password_confirmation', 'sandi-baru-sekali')
        ->press('Simpan kata sandi')
        ->assertPathIs('/pemohon');

    expect(Hash::check('sandi-baru-sekali', $applicant->fresh()->password))->toBeTrue();

    // --- The new password works at the login form, the old one does not
    $page->press('Keluar')
        ->assertPathIs('/pemohon/masuk')
        ->fill('email', 'budi@nusantara.test')
        ->fill('password', 'sandi-lama-sekali')
        ->press('Masuk')
        ->assertPathIs('/pemohon/masuk')
        ->fill('password', 'sandi-baru-sekali')
        ->press('Masuk')
        ->assertPathIs('/pemohon');
});
