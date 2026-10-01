<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Mail\ApplicantInvitationMail;
use Modules\Platform\App\Infrastructure\Mail\ApplicantPasswordResetMail;
use Modules\Platform\App\Infrastructure\Mail\ApplicantSchoolResetMail;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantApplicationFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Fase 4 Tahap 4: the provider invites an applicant from the console,
 * and an applicant can reset a forgotten password. Both set the password
 * through a signed, single-use link.
 */

/**
 * The path (with signature) of an invitation or reset link, as the mail
 * would carry it after the host.
 */
function setupPath(string $route, Applicant $applicant, int $minutes = 60): string
{
    return URL::temporarySignedRoute(
        $route,
        now()->addMinutes($minutes),
        ['applicant' => $applicant->id, 'hash' => $applicant->credentialHash()],
        absolute: false,
    );
}

function asProvider(): void
{
    actingAs(ProviderUserFactory::new()->create(), 'provider');
}

// ---- Undangan dari console

it('invites an applicant: account without password, invitation mailed to the central host', function () {
    Mail::fake();
    asProvider();

    post('http://console.localhost/applicants', ['name' => 'Budi Kepsek', 'email' => 'Budi@Nusantara.test'])
        ->assertRedirect()
        ->assertSessionHas('status');

    $applicant = Applicant::query()->sole();

    expect($applicant->email)->toBe('budi@nusantara.test')
        ->and($applicant->password)->toBeNull()
        ->and($applicant->hasVerifiedEmail())->toBeFalse();

    Mail::assertQueued(
        ApplicantInvitationMail::class,
        fn (ApplicantInvitationMail $mail) => $mail->hasTo('budi@nusantara.test')
            && str_contains($mail->invitationUrl, "/pemohon/undangan/{$applicant->id}/")
            && str_contains($mail->invitationUrl, 'signature=')
            // Issued on the console host, but the link must not point there.
            && ! str_contains($mail->invitationUrl, 'console.'),
    );
});

it('refuses to invite an email that is already an applicant', function () {
    ApplicantFactory::new()->create(['email' => 'sudah@ada.test']);
    asProvider();

    post('http://console.localhost/applicants', ['name' => 'Lagi', 'email' => 'SUDAH@ada.test'])
        ->assertSessionHasErrors('email');

    expect(Applicant::query()->count())->toBe(1);
});

it('lists applicants with where they stand', function () {
    ApplicantFactory::new()->create(['name' => 'Diundang', 'password' => null, 'email_verified_at' => null]);
    ApplicantFactory::new()->unverified()->create(['name' => 'Belum Verifikasi']);
    ApplicantFactory::new()->create(['name' => 'Baru Daftar']);
    $pending = ApplicantFactory::new()->create(['name' => 'Menunggu']);
    TenantApplicationFactory::new()->forApplicant($pending)->create(['school_name' => 'SMA Menunggu']);
    ApplicantFactory::new()->create(['name' => 'Disetujui', 'password' => null])
        ->forceFill(['tenant_id' => TenantFactory::new()->create()->id])->save();
    asProvider();

    get('http://console.localhost/applicants')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Platform/Applicants/Index')
                ->has('applicants', 5)
                ->where('applicants', fn ($rows) => collect($rows)->pluck('state', 'name')->all() === [
                    'Disetujui' => 'approved',
                    'Menunggu' => 'pending',
                    'Baru Daftar' => 'registered',
                    'Belum Verifikasi' => 'unverified',
                    'Diundang' => 'invited',
                ])
                ->where('applicants.1.schoolName', 'SMA Menunggu')
                ->where('applicants.4.canResendInvitation', true)
                ->where('applicants.0.canResendInvitation', false),
        );
});

it('resends an invitation only while the account is not activated', function () {
    Mail::fake();
    $invited = ApplicantFactory::new()->create(['password' => null, 'email_verified_at' => null]);
    $active = ApplicantFactory::new()->create();
    asProvider();

    post("http://console.localhost/applicants/{$invited->id}/invite")->assertSessionHas('status');
    post("http://console.localhost/applicants/{$active->id}/invite")->assertSessionHasErrors('applicant');

    Mail::assertQueued(ApplicantInvitationMail::class, 1);
});

it('keeps the applicant console to provider staff', function () {
    get('http://console.localhost/applicants')->assertRedirect(route('platform.login'));
    post('http://console.localhost/applicants', ['name' => 'X', 'email' => 'x@x.test'])
        ->assertRedirect(route('platform.login'));

    expect(Applicant::query()->count())->toBe(0);
});

// ---- Menerima undangan

it('lets an invited applicant set a password, which verifies the email and signs them in', function () {
    $applicant = ApplicantFactory::new()->create(['name' => 'Budi', 'password' => null, 'email_verified_at' => null]);
    $path = setupPath('applicant.invitation', $applicant);

    get("http://localhost{$path}")
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Platform/Applicant/SetPassword')
                ->where('email', $applicant->email)
                ->where('invitation', true)
                ->where('action', $path),
        );

    post("http://localhost{$path}", ['password' => 'rahasia-sekali', 'password_confirmation' => 'rahasia-sekali'])
        ->assertRedirect(route('applicant.home'));

    $applicant->refresh();

    expect(Hash::check('rahasia-sekali', $applicant->password))->toBeTrue()
        ->and($applicant->hasVerifiedEmail())->toBeTrue();
    assertAuthenticatedAs($applicant, 'applicant');
});

it('makes a setup link single-use', function () {
    $applicant = ApplicantFactory::new()->create(['password' => null, 'email_verified_at' => null]);
    $path = setupPath('applicant.invitation', $applicant);

    post("http://localhost{$path}", ['password' => 'rahasia-sekali', 'password_confirmation' => 'rahasia-sekali'])
        ->assertRedirect();

    // Same link again: the password it was issued against has changed.
    get("http://localhost{$path}")->assertForbidden();
    post("http://localhost{$path}", ['password' => 'lain-lagi-sekali', 'password_confirmation' => 'lain-lagi-sekali'])
        ->assertForbidden();

    expect(Hash::check('rahasia-sekali', $applicant->fresh()->password))->toBeTrue();
});

it('rejects an expired or altered setup link, and one for an approved applicant', function () {
    $applicant = ApplicantFactory::new()->create(['password' => null]);

    get('http://localhost'.setupPath('applicant.invitation', $applicant, -1))->assertForbidden();
    get('http://localhost'.setupPath('applicant.invitation', $applicant).'x')->assertForbidden();
    // A reset signature does not open the invitation route.
    get('http://localhost'.str_replace('atur-ulang', 'undangan', setupPath('applicant.password.reset', $applicant)))
        ->assertForbidden();

    $approved = ApplicantFactory::new()->create(['password' => null]);
    $approved->forceFill(['tenant_id' => TenantFactory::new()->create()->id])->save();

    get('http://localhost'.setupPath('applicant.password.reset', $approved))->assertForbidden();
});

it('validates the new password', function () {
    $applicant = ApplicantFactory::new()->create(['password' => null]);

    post('http://localhost'.setupPath('applicant.invitation', $applicant), ['password' => 'pendek', 'password_confirmation' => 'beda'])
        ->assertSessionHasErrors('password');

    expect($applicant->fresh()->password)->toBeNull();
    assertGuest('applicant');
});

// ---- Lupa kata sandi

it('answers the forgot-password form the same for every email', function () {
    Mail::fake();
    ApplicantFactory::new()->create(['email' => 'budi@nusantara.test']);

    $known = post('http://localhost/pemohon/lupa-sandi', ['email' => 'Budi@Nusantara.test']);
    $unknown = post('http://localhost/pemohon/lupa-sandi', ['email' => 'tidak-ada@nusantara.test']);

    $known->assertSessionHasNoErrors()->assertSessionHas('status');
    $unknown->assertSessionHasNoErrors()->assertSessionHas('status');

    Mail::assertQueued(ApplicantPasswordResetMail::class, 1);
});

it('mails a reset link that sets a new password', function () {
    Mail::fake();
    $applicant = ApplicantFactory::new()->create(['email' => 'budi@nusantara.test']);

    post('http://localhost/pemohon/lupa-sandi', ['email' => 'budi@nusantara.test']);

    $url = null;
    Mail::assertQueued(ApplicantPasswordResetMail::class, function (ApplicantPasswordResetMail $mail) use (&$url) {
        $url = $mail->resetUrl;

        return $mail->hasTo('budi@nusantara.test');
    });

    $path = (string) preg_replace('#^https?://[^/]+#', '', (string) $url);

    get("http://localhost{$path}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('invitation', false));

    post("http://localhost{$path}", ['password' => 'sandi-baru-sekali', 'password_confirmation' => 'sandi-baru-sekali'])
        ->assertRedirect(route('applicant.home'));

    expect(Hash::check('sandi-baru-sekali', $applicant->fresh()->password))->toBeTrue();
});

it('sends the invitation again when an invited applicant forgets the password they never set', function () {
    Mail::fake();
    ApplicantFactory::new()->create(['email' => 'undangan@nusantara.test', 'password' => null]);

    post('http://localhost/pemohon/lupa-sandi', ['email' => 'undangan@nusantara.test']);

    Mail::assertQueued(ApplicantInvitationMail::class, 1);
    Mail::assertNotQueued(ApplicantPasswordResetMail::class);
});

it('points an approved applicant at the school reset instead', function () {
    Mail::fake();
    $tenant = TenantFactory::new()->create();
    ApplicantFactory::new()->create(['email' => 'admin@nusantara.test', 'password' => null])
        ->forceFill(['tenant_id' => $tenant->id])->save();

    post('http://localhost/pemohon/lupa-sandi', ['email' => 'admin@nusantara.test'])->assertSessionHas('status');

    Mail::assertNotQueued(ApplicantPasswordResetMail::class);
    Mail::assertQueued(
        ApplicantSchoolResetMail::class,
        fn (ApplicantSchoolResetMail $mail) => $mail->hasTo('admin@nusantara.test')
            && $mail->schoolCode === $tenant->id
            && str_contains($mail->schoolResetUrl, '/forgot-password')
            && str_contains($mail->schoolResetUrl, "school={$tenant->id}"),
    );
});
