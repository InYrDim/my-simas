<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Mail\ApplicantVerifyMail;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Applicant accounts (Fase 4 Tahap 1): the account a person creates at
 * /daftar-sekolah to register a school. A central identity on its own
 * 'applicant' guard — never a school user, never a provider user.
 */
function verificationUrl(Applicant $applicant, int $minutes = 60): string
{
    return URL::temporarySignedRoute(
        'applicant.verify',
        now()->addMinutes($minutes),
        ['applicant' => $applicant->id, 'hash' => $applicant->verificationHash()],
    );
}

// ---- Daftar

it('renders the registration form on the central host', function () {
    get('http://localhost/daftar-sekolah')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Platform/Applicant/Register'));
});

it('creates an unverified applicant, signs them in and mails the verification link', function () {
    Mail::fake();

    post('http://localhost/daftar-sekolah', [
        'name' => 'Budi Kepsek',
        'email' => 'Budi@Nusantara.test',
        'password' => 'rahasia-sekali',
        'password_confirmation' => 'rahasia-sekali',
    ])->assertRedirect(route('applicant.verify.notice'));

    $applicant = Applicant::query()->sole();

    expect($applicant->email)->toBe('budi@nusantara.test')
        ->and($applicant->hasVerifiedEmail())->toBeFalse()
        ->and($applicant->tenant_id)->toBeNull()
        ->and(Hash::check('rahasia-sekali', $applicant->password))->toBeTrue()
        // An applicant is not a school user and no tenant exists yet.
        ->and(DB::table('users')->count())->toBe(0)
        ->and(DB::table('tenants')->count())->toBe(0);

    assertAuthenticatedAs($applicant, 'applicant');

    Mail::assertQueued(
        ApplicantVerifyMail::class,
        fn (ApplicantVerifyMail $mail) => $mail->hasTo('budi@nusantara.test')
            && str_contains($mail->verifyUrl, "/pemohon/verifikasi/{$applicant->id}/")
            && str_contains($mail->verifyUrl, 'signature='),
    );
});

it('validates registration and keeps emails unique', function () {
    ApplicantFactory::new()->create(['email' => 'sudah@ada.test']);

    post('http://localhost/daftar-sekolah', [
        'name' => '',
        'email' => 'SUDAH@ada.test',
        'password' => 'pendek',
        'password_confirmation' => 'beda',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors(['name', 'email', 'password']);

    expect(Applicant::query()->count())->toBe(1);
});

it('silently drops honeypot registrations with a generic success', function () {
    Mail::fake();

    post('http://localhost/daftar-sekolah', [
        'name' => 'Bot',
        'email' => 'bot@spam.test',
        'password' => 'rahasia-sekali',
        'password_confirmation' => 'rahasia-sekali',
        'website' => 'http://spam.example',
    ])
        ->assertRedirect(route('applicant.login'))
        ->assertSessionHas('status');

    expect(Applicant::query()->count())->toBe(0);
    Mail::assertNothingQueued();
    assertGuest('applicant');
});

it('sends a signed-in applicant from the registration form to onboarding', function () {
    actingAs(ApplicantFactory::new()->create(), 'applicant');

    get('http://localhost/daftar-sekolah')->assertRedirect(route('applicant.home'));
    get('http://localhost/pemohon/masuk')->assertRedirect(route('applicant.home'));
});

// ---- Verifikasi email

it('holds an unverified applicant at the verification notice', function () {
    $applicant = ApplicantFactory::new()->unverified()->create();
    actingAs($applicant, 'applicant');

    get('http://localhost/pemohon/verifikasi')
        ->assertOk()
        ->assertInertia(
            fn ($page) => $page
                ->component('Platform/Applicant/VerifyNotice')
                ->where('email', $applicant->email),
        );
});

it('verifies the email through the signed link, even without a session', function () {
    $applicant = ApplicantFactory::new()->unverified()->create();

    get(verificationUrl($applicant))
        ->assertRedirect(route('applicant.login'))
        ->assertSessionHas('status');

    expect($applicant->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('lands a signed-in applicant on onboarding after verifying', function () {
    $applicant = ApplicantFactory::new()->unverified()->create();
    actingAs($applicant, 'applicant');

    get(verificationUrl($applicant))->assertRedirect(route('applicant.home'));

    // The guard still holds the pre-verification instance in this test
    // process; a real request re-reads the applicant from the session.
    actingAs($applicant->fresh(), 'applicant');
    get('http://localhost/pemohon/verifikasi')->assertRedirect(route('applicant.home'));
});

it('rejects an expired, altered or mismatched verification link', function () {
    $applicant = ApplicantFactory::new()->unverified()->create();
    $other = ApplicantFactory::new()->unverified()->create();

    get(verificationUrl($applicant, -1))->assertForbidden();
    get(verificationUrl($applicant).'x')->assertForbidden();

    // Correctly signed, but the hash belongs to another address.
    get(URL::temporarySignedRoute('applicant.verify', now()->addHour(), [
        'applicant' => $applicant->id,
        'hash' => $other->verificationHash(),
    ]))->assertForbidden();

    expect($applicant->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification link on request', function () {
    Mail::fake();
    $applicant = ApplicantFactory::new()->unverified()->create();
    actingAs($applicant, 'applicant');

    post('http://localhost/pemohon/verifikasi/kirim-ulang')->assertSessionHas('status');

    Mail::assertQueued(ApplicantVerifyMail::class, fn (ApplicantVerifyMail $mail) => $mail->hasTo($applicant->email));
});

// ---- Masuk / keluar

it('signs an applicant in and out', function () {
    $applicant = ApplicantFactory::new()->create(['email' => 'budi@nusantara.test']);

    post('http://localhost/pemohon/masuk', ['email' => 'Budi@Nusantara.test', 'password' => 'password'])
        ->assertRedirect(route('applicant.home'));
    assertAuthenticatedAs($applicant, 'applicant');

    post('http://localhost/pemohon/keluar')->assertRedirect(route('applicant.login'));
    assertGuest('applicant');
});

it('answers every failed login with the same generic error', function () {
    ApplicantFactory::new()->create(['email' => 'budi@nusantara.test']);
    ApplicantFactory::new()->create(['email' => 'undangan@nusantara.test', 'password' => null]);

    foreach ([
        ['email' => 'budi@nusantara.test', 'password' => 'salah'],
        ['email' => 'tidak-ada@nusantara.test', 'password' => 'password'],
        ['email' => 'undangan@nusantara.test', 'password' => ''],
        ['email' => 'undangan@nusantara.test', 'password' => 'password'],
    ] as $credentials) {
        $response = post('http://localhost/pemohon/masuk', $credentials)->assertSessionHasErrors();
        assertGuest('applicant');
    }

    expect(session('errors')->first('email'))->toBe(__('auth.failed'));
});

it('throttles repeated failed logins per email and address', function () {
    ApplicantFactory::new()->create(['email' => 'budi@nusantara.test']);

    foreach (range(1, 5) as $attempt) {
        post('http://localhost/pemohon/masuk', ['email' => 'budi@nusantara.test', 'password' => 'salah']);
    }

    post('http://localhost/pemohon/masuk', ['email' => 'budi@nusantara.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    assertGuest('applicant');
});

// ---- Pemisahan guard

it('keeps the applicant guard apart from school and provider sessions', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'guard-sekolah']);
    $user = UserFactory::new()->forTenant($tenant->id)->create();

    // A school user and a provider user are guests on the applicant pages.
    actingAs($user);
    get(school($tenant->slug, '/pemohon'))->assertRedirect(route('applicant.login'));

    Auth::guard('web')->logout();
    actingAs(ProviderUserFactory::new()->create(), 'provider');
    get('http://localhost/pemohon')->assertRedirect(route('applicant.login'));
});

it('does not let an applicant session open school pages', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'guard-pemohon']);
    $applicant = ApplicantFactory::new()->create();

    // A real login, not actingAs(): actingAs() would also make
    // 'applicant' the default guard, which no real request does.
    post('http://localhost/pemohon/masuk', ['email' => $applicant->email, 'password' => 'password'])
        ->assertRedirect(route('applicant.home'));
    assertAuthenticatedAs($applicant, 'applicant');

    get(school($tenant->slug, '/beranda'))->assertRedirect(route('login'));
    get(school($tenant->slug, '/master/siswa'))->assertRedirect(route('login'));
});

it('sends an applicant from the front door to onboarding', function () {
    actingAs(ApplicantFactory::new()->create(), 'applicant');
    Auth::shouldUse('web');

    get('http://localhost/')->assertRedirect(route('applicant.home'));
});
