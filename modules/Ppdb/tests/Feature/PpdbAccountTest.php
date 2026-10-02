<?php

namespace Modules\Ppdb\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Infrastructure\Mail\AccountPasswordResetMail;
use Modules\Ppdb\App\Infrastructure\Mail\AccountVerificationMail;
use Modules\Ppdb\Database\Factories\PpdbAccountFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/Support/helpers.php';

/*
 * The applicant's own account: a central identity on the `ppdb` guard,
 * never a school user. These pages need no school at all.
 */

const ACCOUNT_URL = 'http://localhost/calon-siswa';

/**
 * The signed RELATIVE path (with its query) of a link for the account.
 */
function accountLink(string $route, PpdbAccount $account, string $hash, int $minutes = 60): string
{
    return URL::temporarySignedRoute($route, now()->addMinutes($minutes), ['account' => $account->id, 'hash' => $hash], absolute: false);
}

/**
 * The path and query of a mailed absolute URL.
 */
function mailedPath(string $url): string
{
    return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
}

// ---- Daftar

it('renders the registration form for a guest on the central host', function () {
    get(ACCOUNT_URL.'/daftar')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Ppdb/Account/Register'));
});

it('creates an unverified account, signs it in and mails the verification link', function () {
    Mail::fake();

    post(ACCOUNT_URL.'/daftar', [
        'name' => 'Siti Aminah',
        'email' => 'Siti@Contoh.test',
        'password' => 'rahasia-sekali',
        'password_confirmation' => 'rahasia-sekali',
    ])->assertRedirect(route('ppdb.account.verify.notice'));

    $account = PpdbAccount::query()->sole();

    expect($account->email)->toBe('siti@contoh.test')
        ->and($account->name)->toBe('Siti Aminah')
        ->and($account->hasVerifiedEmail())->toBeFalse()
        ->and($account->tenant_id)->toBeNull()
        ->and(Hash::check('rahasia-sekali', $account->password))->toBeTrue()
        // An applicant is not a school user, and no school is involved.
        ->and(\DB::table('users')->count())->toBe(0);

    assertAuthenticatedAs($account, 'ppdb');

    Mail::assertQueued(
        AccountVerificationMail::class,
        fn (AccountVerificationMail $mail) => $mail->hasTo('siti@contoh.test')
            && str_contains($mail->verifyUrl, "/calon-siswa/verifikasi/{$account->id}/")
            && str_contains($mail->verifyUrl, 'signature='),
    );
});

it('refuses a registration with missing data, a repeated email or a weak password', function () {
    PpdbAccountFactory::new()->create(['email' => 'sudah@ada.test']);

    post(ACCOUNT_URL.'/daftar', [
        'name' => '',
        'email' => 'SUDAH@ada.test',
        'password' => 'pendek',
        'password_confirmation' => 'beda',
    ])->assertSessionHasErrors(['name', 'email', 'password']);

    expect(PpdbAccount::query()->count())->toBe(1);
});

it('silently drops honeypot registrations with a generic answer', function () {
    Mail::fake();

    post(ACCOUNT_URL.'/daftar', [
        'name' => 'Bot',
        'email' => 'bot@spam.test',
        'password' => 'rahasia-sekali',
        'password_confirmation' => 'rahasia-sekali',
        'website' => 'http://spam.example',
    ])->assertRedirect(route('ppdb.account.login'))->assertSessionHas('status');

    expect(PpdbAccount::query()->count())->toBe(0);
    Mail::assertNothingQueued();
    assertGuest('ppdb');
});

it('throttles registrations from one address', function () {
    foreach (range(1, 5) as $index) {
        post(ACCOUNT_URL.'/daftar', ['name' => '', 'email' => "bot{$index}@spam.test"])->assertSessionHasErrors('name');
    }

    post(ACCOUNT_URL.'/daftar', ['name' => 'Siti', 'email' => 'siti@contoh.test', 'password' => 'rahasia-sekali', 'password_confirmation' => 'rahasia-sekali'])
        ->assertStatus(429);

    expect(PpdbAccount::query()->count())->toBe(0);
});

it('sends a signed-in applicant away from the registration and login forms', function () {
    actingAs(PpdbAccountFactory::new()->create(), 'ppdb');

    get(ACCOUNT_URL.'/daftar')->assertRedirect(route('ppdb.account.home'));
    get(ACCOUNT_URL.'/masuk')->assertRedirect(route('ppdb.account.home'));
});

// ---- Verifikasi email

it('holds an unverified account at the verification notice', function () {
    $account = PpdbAccountFactory::new()->unverified()->create();
    actingAs($account, 'ppdb');

    get(ACCOUNT_URL)->assertRedirect(route('ppdb.account.verify.notice'));
    get(ACCOUNT_URL.'/verifikasi')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Account/VerifyNotice')
        ->where('email', $account->email)
    );
});

it('opens the applicant page for a verified account', function () {
    $account = PpdbAccountFactory::new()->create(['name' => 'Siti Aminah', 'email' => 'siti@contoh.test']);
    actingAs($account, 'ppdb');

    get(ACCOUNT_URL)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Account/Home')
        ->where('account.name', 'Siti Aminah')
        ->where('account.email', 'siti@contoh.test')
    );
});

it('verifies the email through the signed link, even without a session', function () {
    $account = PpdbAccountFactory::new()->unverified()->create();

    get('http://localhost'.accountLink('ppdb.account.verify', $account, $account->verificationHash()))
        ->assertRedirect(route('ppdb.account.login'))
        ->assertSessionHas('status');

    expect($account->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('lands a signed-in applicant on their page after verifying', function () {
    $account = PpdbAccountFactory::new()->unverified()->create();
    actingAs($account, 'ppdb');

    get('http://localhost'.accountLink('ppdb.account.verify', $account, $account->verificationHash()))
        ->assertRedirect(route('ppdb.account.home'));
});

it('rejects an expired, altered or mismatched verification link', function () {
    $account = PpdbAccountFactory::new()->unverified()->create();
    $other = PpdbAccountFactory::new()->unverified()->create();

    get('http://localhost'.accountLink('ppdb.account.verify', $account, $account->verificationHash(), -1))->assertForbidden();
    get('http://localhost'.accountLink('ppdb.account.verify', $account, $account->verificationHash()).'x')->assertForbidden();
    // Correctly signed, but the hash belongs to another address.
    get('http://localhost'.accountLink('ppdb.account.verify', $account, $other->verificationHash()))->assertForbidden();

    expect($account->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification link on request', function () {
    Mail::fake();
    $account = PpdbAccountFactory::new()->unverified()->create();
    actingAs($account, 'ppdb');

    post(ACCOUNT_URL.'/verifikasi/kirim-ulang')->assertSessionHas('status');

    Mail::assertQueued(AccountVerificationMail::class, fn (AccountVerificationMail $mail) => $mail->hasTo($account->email));
});

it('does not send another link to an account that is verified already', function () {
    Mail::fake();
    actingAs(PpdbAccountFactory::new()->create(), 'ppdb');

    post(ACCOUNT_URL.'/verifikasi/kirim-ulang')->assertRedirect(route('ppdb.account.home'));

    Mail::assertNothingQueued();
});

// ---- Masuk / keluar

it('signs an applicant in and out', function () {
    $account = PpdbAccountFactory::new()->create(['email' => 'siti@contoh.test']);

    post(ACCOUNT_URL.'/masuk', ['email' => 'Siti@Contoh.test', 'password' => 'password'])
        ->assertRedirect(route('ppdb.account.home'));
    assertAuthenticatedAs($account, 'ppdb');

    post(ACCOUNT_URL.'/keluar')->assertRedirect(route('ppdb.account.login'));
    assertGuest('ppdb');
});

it('follows an applicant page the applicant was sent from after signing in', function () {
    PpdbAccountFactory::new()->create(['email' => 'siti@contoh.test']);

    $this->withSession(['url.intended' => 'http://localhost/calon-siswa/gabung?school=01arz3ndektsv4rrffq69g5fav'])
        ->post(ACCOUNT_URL.'/masuk', ['email' => 'siti@contoh.test', 'password' => 'password'])
        ->assertRedirect('http://localhost/calon-siswa/gabung?school=01arz3ndektsv4rrffq69g5fav');
});

it('ignores a remembered address that belongs to a school page', function (string $intended) {
    PpdbAccountFactory::new()->create(['email' => 'siti@contoh.test']);

    // The session's `url.intended` is shared by every guard: an applicant who
    // opened a school page and was sent to the school login must not be
    // carried back there by signing in as an applicant.
    $this->withSession(['url.intended' => $intended])
        ->post(ACCOUNT_URL.'/masuk', ['email' => 'siti@contoh.test', 'password' => 'password'])
        ->assertRedirect(route('ppdb.account.home'))
        ->assertSessionMissing('url.intended');
})->with([
    'committee page' => ['http://localhost/ppdb'],
    'school home' => ['http://localhost/beranda'],
    'a look-alike path' => ['http://localhost/ppdb/calon-siswa'],
    'a longer first segment' => ['http://localhost/calon-siswa-lain'],
    'nonsense' => ['bukan-alamat'],
]);

it('ends only the applicant session on sign out', function () {
    actingAs(PpdbAccountFactory::new()->create(), 'ppdb');

    $this->withSession(['penanda' => 'sesi-sekolah-tetap'])
        ->post(ACCOUNT_URL.'/keluar')
        ->assertSessionHas('penanda', 'sesi-sekolah-tetap');

    assertGuest('ppdb');
});

it('answers every failed login with the same generic error', function () {
    PpdbAccountFactory::new()->create(['email' => 'siti@contoh.test']);

    // A wrong password and an email nobody has give the very same answer.
    foreach ([
        ['email' => 'siti@contoh.test', 'password' => 'salah'],
        ['email' => 'tidak-ada@contoh.test', 'password' => 'salah'],
        ['email' => 'tidak-ada@contoh.test', 'password' => 'password'],
    ] as $credentials) {
        post(ACCOUNT_URL.'/masuk', $credentials)->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
        assertGuest('ppdb');
    }
});

it('throttles repeated failed logins per email and address', function () {
    PpdbAccountFactory::new()->create(['email' => 'siti@contoh.test']);

    foreach (range(1, 5) as $attempt) {
        post(ACCOUNT_URL.'/masuk', ['email' => 'siti@contoh.test', 'password' => 'salah']);
    }

    post(ACCOUNT_URL.'/masuk', ['email' => 'siti@contoh.test', 'password' => 'password'])->assertSessionHasErrors('email');
    assertGuest('ppdb');
});

// ---- Lupa kata sandi

it('mails a reset link, sets a new password from it once, and signs the applicant in', function () {
    Mail::fake();
    $account = PpdbAccountFactory::new()->unverified()->create(['email' => 'siti@contoh.test']);

    post(ACCOUNT_URL.'/lupa-sandi', ['email' => 'Siti@Contoh.test'])->assertSessionHas('status');

    $url = null;
    Mail::assertQueued(AccountPasswordResetMail::class, function (AccountPasswordResetMail $mail) use (&$url) {
        $url = $mail->resetUrl;

        return $mail->hasTo('siti@contoh.test');
    });
    $path = 'http://localhost'.mailedPath($url);

    get($path)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Ppdb/Account/SetPassword')
        ->where('email', 'siti@contoh.test')
    );

    post($path, ['password' => 'kata-sandi-baru', 'password_confirmation' => 'kata-sandi-baru'])
        ->assertRedirect(route('ppdb.account.home'));

    $fresh = $account->fresh();
    expect(Hash::check('kata-sandi-baru', $fresh->password))->toBeTrue()
        // Opening the link proves control of the mailbox.
        ->and($fresh->hasVerifiedEmail())->toBeTrue();
    assertAuthenticatedAs($fresh, 'ppdb');

    // The link is tied to the old password: it works once.
    get($path)->assertForbidden();
});

it('answers a reset request the same way when the email has no account', function () {
    Mail::fake();

    post(ACCOUNT_URL.'/lupa-sandi', ['email' => 'tidak-ada@contoh.test'])
        ->assertSessionHas('status', 'Jika email terdaftar, petunjuk atur ulang kata sandi telah dikirim.');

    Mail::assertNothingQueued();
});

it('rejects an expired or altered reset link and a weak new password', function () {
    $account = PpdbAccountFactory::new()->create();

    get('http://localhost'.accountLink('ppdb.account.password.reset', $account, $account->credentialHash(), -1))->assertForbidden();
    get('http://localhost'.accountLink('ppdb.account.password.reset', $account, 'bukan-hash'))->assertForbidden();

    $path = 'http://localhost'.accountLink('ppdb.account.password.reset', $account, $account->credentialHash());
    post($path, ['password' => 'pendek', 'password_confirmation' => 'pendek'])->assertSessionHasErrors('password');

    expect(Hash::check('password', $account->fresh()->password))->toBeTrue();
});

// ---- Pemisahan guard

it('keeps the applicant guard apart from school, provider and school-applicant sessions', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'guard-sekolah-ppdb']);
    $user = UserFactory::new()->forTenant($tenant->id)->create();

    // A school user is a guest on the applicant pages.
    actingAs($user);
    get(school($tenant->slug, '/calon-siswa'))->assertRedirect(route('ppdb.account.login'));

    Auth::guard('web')->logout();

    // So is someone applying to register a school.
    actingAs(ApplicantFactory::new()->create(), 'applicant');
    get(ACCOUNT_URL)->assertRedirect(route('ppdb.account.login'));
});

it('does not let an applicant session open school or committee pages', function () {
    $tenant = ppdbTenant(slug: 'guard-ppdb-sekolah');
    $account = PpdbAccountFactory::new()->create(['email' => 'siti@contoh.test']);
    Auth::guard('web')->logout();

    // A real login, not actingAs(): actingAs() would also make 'ppdb' the
    // default guard, which no real request does.
    post(ACCOUNT_URL.'/masuk', ['email' => 'siti@contoh.test', 'password' => 'password'])
        ->assertRedirect(route('ppdb.account.home'));
    assertAuthenticatedAs($account, 'ppdb');

    get(school($tenant->slug, '/beranda'))->assertRedirect(route('login'));
    get(school($tenant->slug, '/ppdb'))->assertRedirect(route('login'));
    get(school($tenant->slug, '/ppdb/pendaftar'))->assertRedirect(route('login'));
});

it('works without any school and without the module being enabled for one', function () {
    // No tenant exists at all: the whole account flow is central.
    post(ACCOUNT_URL.'/daftar', [
        'name' => 'Siti Aminah',
        'email' => 'siti@contoh.test',
        'password' => 'rahasia-sekali',
        'password_confirmation' => 'rahasia-sekali',
    ])->assertRedirect(route('ppdb.account.verify.notice'));

    expect(\DB::table('tenants')->count())->toBe(0)
        ->and(PpdbAccount::query()->sole()->tenant_id)->toBeNull();
});
