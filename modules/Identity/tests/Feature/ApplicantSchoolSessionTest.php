<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Infrastructure\Auth\DefaultSchoolSessionOpener;
use Modules\Identity\App\Infrastructure\Mail\SetPasswordMail;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\Events\TenantApproved;
use Modules\Platform\App\Contracts\SchoolSessionOpener;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Applicant;
use Modules\Platform\App\Infrastructure\Mail\ApplicationApprovedMail;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantApplicationFactory;
use Modules\Platform\Database\Factories\TenantFactory;
use RuntimeException;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Fase 4 Tahap 3: the bridge from an approved application to the school.
 * Approval hands the applicant's password to the school admin account
 * (moved, not copied), and from then on the applicant login opens the
 * SCHOOL session through Platform's SchoolSessionOpener, which Identity
 * implements.
 */

/**
 * An applicant with a pending application, approved by a provider.
 *
 * @return array{0: Applicant, 1: string} the applicant and their tenant id
 */
function approvedApplicant(string $email = 'budi@nusantara.test', string $password = 'rahasia-sekali'): array
{
    PlanFactory::new()->create(['key' => 'starter']);

    $applicant = ApplicantFactory::new()->create(['name' => 'Budi Kepsek', 'email' => $email, 'password' => $password]);
    $application = TenantApplicationFactory::new()->forApplicant($applicant)->create(['plan_key' => 'starter']);

    app(TenantApplications::class)->approve($application->id, ProviderUserFactory::new()->create()->id);

    $applicant->refresh();

    return [$applicant, (string) $applicant->tenant_id];
}

function schoolAdmin(string $tenantId, string $email): User
{
    return app(TenantContext::class)->run($tenantId, fn () => User::query()->where('email', $email)->sole());
}

// ---- ACC memindahkan password

it('moves the applicant password to the school admin at approval', function () {
    Mail::fake();

    [$applicant, $tenantId] = approvedApplicant();
    $admin = schoolAdmin($tenantId, 'budi@nusantara.test');

    expect($tenantId)->not->toBe('')
        // Moved, not copied: the applicant keeps no credential.
        ->and($applicant->password)->toBeNull()
        ->and(Hash::check('rahasia-sekali', $admin->password))->toBeTrue()
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->name)->toBe('Budi Kepsek')
        ->and(app(TenantContext::class)->run($tenantId, fn () => $admin->hasTenantRole('admin-sekolah')))->toBeTrue();

    // No set-password link: the admin already has their password.
    Mail::assertNotQueued(SetPasswordMail::class);
    expect(DB::table('password_reset_tokens')->count())->toBe(0);

    Mail::assertQueued(ApplicationApprovedMail::class, fn (ApplicationApprovedMail $mail) => $mail->passwordReady === true);
});

it('still sends the set-password link for an application without an applicant account', function () {
    Mail::fake();
    PlanFactory::new()->create(['key' => 'starter']);
    $application = TenantApplicationFactory::new()->create(['applicant_email' => 'lama@tanpa-akun.test']);

    app(TenantApplications::class)->approve($application->id, ProviderUserFactory::new()->create()->id);

    Mail::assertQueued(SetPasswordMail::class, fn (SetPasswordMail $mail) => $mail->hasTo('lama@tanpa-akun.test'));
    expect(DB::table('users')->where('email', 'lama@tanpa-akun.test')->value('password'))->toBeNull();
});

it('rolls the hand-over back when provisioning the admin fails', function () {
    PlanFactory::new()->create(['key' => 'starter']);
    $applicant = ApplicantFactory::new()->create(['password' => 'rahasia-sekali']);
    $application = TenantApplicationFactory::new()->forApplicant($applicant)->create();

    // A later TenantApproved listener explodes, AFTER Identity's listener
    // already gave the admin the applicant's password.
    Event::listen(TenantApproved::class, function (): never {
        throw new RuntimeException('listener gagal');
    });

    expect(fn () => app(TenantApplications::class)->approve($application->id, ProviderUserFactory::new()->create()->id))
        ->toThrow(RuntimeException::class, 'listener gagal');

    $applicant->refresh();

    expect(DB::table('tenants')->count())->toBe(0)
        ->and(DB::table('users')->count())->toBe(0)
        ->and($applicant->tenant_id)->toBeNull()
        ->and(Hash::check('rahasia-sekali', (string) $applicant->password))->toBeTrue()
        ->and($application->fresh()->status->value)->toBe('pending');
});

// ---- Login pemohon membuka sekolah

it('opens the school session when an approved applicant logs in', function () {
    [, $tenantId] = approvedApplicant();
    $admin = schoolAdmin($tenantId, 'budi@nusantara.test');

    post('http://localhost/pemohon/masuk', ['email' => 'Budi@Nusantara.test', 'password' => 'rahasia-sekali'])
        ->assertRedirect('/')
        ->assertSessionHas('tenant_id', $tenantId);

    assertAuthenticatedAs($admin, 'web');
    assertGuest('applicant');

    // The front door and the landing page now belong to the school.
    get('http://localhost/')->assertRedirect(route('home'));
    get('http://localhost/beranda')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Core/Beranda'));
});

it('also lets the new admin in through the ordinary school login', function () {
    [, $tenantId] = approvedApplicant();

    post('http://localhost/login', ['school' => $tenantId, 'email' => 'budi@nusantara.test', 'password' => 'rahasia-sekali'])
        ->assertRedirect(route('home'));

    assertAuthenticatedAs(schoolAdmin($tenantId, 'budi@nusantara.test'), 'web');
});

it('refuses an approved applicant with a wrong password, generically', function () {
    approvedApplicant();

    post('http://localhost/pemohon/masuk', ['email' => 'budi@nusantara.test', 'password' => 'salah'])
        ->assertSessionHasErrors(['email' => __('auth.failed')])
        ->assertSessionMissing('tenant_id');

    assertGuest('web');
    assertGuest('applicant');
});

it('follows the school password after it changes — there is only one credential', function () {
    [, $tenantId] = approvedApplicant();
    $admin = schoolAdmin($tenantId, 'budi@nusantara.test');
    app(TenantContext::class)->run($tenantId, fn () => $admin->forceFill(['password' => 'sandi-baru-sekali'])->save());

    post('http://localhost/pemohon/masuk', ['email' => 'budi@nusantara.test', 'password' => 'rahasia-sekali'])
        ->assertSessionHasErrors('email');
    assertGuest('web');

    post('http://localhost/pemohon/masuk', ['email' => 'budi@nusantara.test', 'password' => 'sandi-baru-sekali'])
        ->assertRedirect('/');
    assertAuthenticatedAs($admin, 'web');
});

it('refuses a deactivated admin and a suspended school', function () {
    [, $tenantId] = approvedApplicant();
    $admin = schoolAdmin($tenantId, 'budi@nusantara.test');

    app(TenantContext::class)->run($tenantId, fn () => $admin->forceFill(['deactivated_at' => now()])->save());
    post('http://localhost/pemohon/masuk', ['email' => 'budi@nusantara.test', 'password' => 'rahasia-sekali'])
        ->assertSessionHasErrors('email');
    assertGuest('web');

    app(TenantContext::class)->run($tenantId, fn () => $admin->forceFill(['deactivated_at' => null])->save());
    DB::table('tenants')->where('id', $tenantId)->update(['status' => 'suspended']);

    post('http://localhost/pemohon/masuk', ['email' => 'budi@nusantara.test', 'password' => 'rahasia-sekali'])
        ->assertSessionHasErrors('email');
    assertGuest('web');
});

it('never opens a school session for an applicant who is not approved', function () {
    PlanFactory::new()->create(['key' => 'starter']);
    $applicant = ApplicantFactory::new()->create(['email' => 'menunggu@nusantara.test', 'password' => 'rahasia-sekali']);
    TenantApplicationFactory::new()->forApplicant($applicant)->create();

    post('http://localhost/pemohon/masuk', ['email' => 'menunggu@nusantara.test', 'password' => 'rahasia-sekali'])
        ->assertRedirect(route('applicant.home'))
        ->assertSessionMissing('tenant_id');

    assertAuthenticatedAs($applicant, 'applicant');
    assertGuest('web');
});

// ---- SchoolSessionOpener (kontrak Platform, implementasi Identity)

it('binds Identity\'s opener over Platform\'s refusing default', function () {
    expect(app(SchoolSessionOpener::class))->toBeInstanceOf(DefaultSchoolSessionOpener::class);
});

it('opens a session only for an active user of that very school', function () {
    $a = TenantFactory::new()->create(['slug' => 'opener-a']);
    $b = TenantFactory::new()->create(['slug' => 'opener-b']);
    UserFactory::new()->forTenant($a->id)->create(['email' => 'sama@opener.test', 'password' => 'sandi-sekolah-a']);
    UserFactory::new()->forTenant($b->id)->create(['email' => 'sama@opener.test', 'password' => 'sandi-sekolah-b']);

    $opener = app(SchoolSessionOpener::class);

    // The right password of the OTHER school's account never opens this one.
    expect($opener->attempt($a->id, 'sama@opener.test', 'sandi-sekolah-b'))->toBeFalse()
        ->and($opener->attempt($a->id, 'tidak-ada@opener.test', 'sandi-sekolah-a'))->toBeFalse()
        ->and($opener->attempt('01HZZZZZZZZZZZZZZZZZZZZZZZ', 'sama@opener.test', 'sandi-sekolah-a'))->toBeFalse();
    assertGuest('web');

    expect($opener->attempt($a->id, 'sama@opener.test', 'sandi-sekolah-a'))->toBeTrue()
        ->and(auth('web')->user()->tenant_id)->toBe($a->id)
        ->and(session('tenant_id'))->toBe($a->id);
});
