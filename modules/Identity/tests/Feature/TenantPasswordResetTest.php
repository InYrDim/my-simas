<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Infrastructure\Mail\ResetPasswordMail;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantUrl;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\from;
use function Pest\Laravel\post;

/**
 * Stage 4 (Fase 2): tenant-aware password reset.
 *
 * - tokens are scoped to (tenant_id, email): tenant A's token is
 *   worthless on tenant B's host even for the same email
 * - the reset flow changes the right user's password and logs them in
 * - expired tokens are refused
 * - unknown emails, deactivated users, and users without a password
 *   get the SAME generic response with NO email sent (anti-enumeration)
 * - email links point at the tenant host (TenantUrl), not central
 */
function resetTenantUser(string $slug, string $email): array
{
    $tenant = Tenant::query()->where('slug', $slug)->first()
        ?? TenantFactory::new()->create(['slug' => $slug]);

    $user = User::factory()->forTenant($tenant->id)->create([
        'email' => $email,
        'password' => 'password123',
    ]);

    return [$tenant, $user];
}

function tokenRowFor(string $tenantId, string $email): ?object
{
    return DB::table('password_reset_tokens')
        ->where('tenant_id', $tenantId)
        ->where('email', $email)
        ->first();
}

it('creates a tenant-scoped token row and emails a tenant-hosted link', function () {
    Mail::fake();

    [$tenant, $user] = resetTenantUser('sekolah-a', 'budi@example.com');

    from('http://sekolah-a.localhost/login')
        ->post('http://sekolah-a.localhost/forgot-password', [
            'email' => 'budi@example.com',
        ])->assertRedirect()
        ->assertSessionHas('status');

    $row = tokenRowFor($tenant->id, 'budi@example.com');

    expect($row)->not->toBeNull()
        // Exactly one tenant's row exists for this email.
        ->and(DB::table('password_reset_tokens')->where('email', 'budi@example.com')->count())->toBe(1);

    // The queued mail carries the tenant-hosted URL (built by the User
    // model via TenantUrl before queueing).
    Mail::assertQueued(ResetPasswordMail::class, function (ResetPasswordMail $mail): bool {
        return str_contains($mail->resetUrl, 'sekolah-a.localhost');
    });
});

it('builds reset links on the tenant host via TenantUrl', function () {
    [$tenant, $user] = resetTenantUser('sekolah-a', 'budi@example.com');

    $root = app(TenantUrl::class)->root($tenant->id);

    expect($root)->toBe('http://sekolah-a.localhost')
        ->and(app(TenantUrl::class)->host($tenant->id))->toBe('sekolah-a.localhost');
});

it('rejects a tenant A token on the tenant B host (same email)', function () {
    [$tenantA, $userA] = resetTenantUser('sekolah-a', 'budi@example.com');
    [$tenantB, $userB] = resetTenantUser('sekolah-b', 'budi@example.com');

    // Mint a token in tenant A's context.
    $token = app(TenantContext::class)->run(
        $tenantA->id,
        fn (): string => PasswordBrokerToken::create($userA),
    );

    expect(tokenRowFor($tenantA->id, 'budi@example.com'))->not->toBeNull()
        ->and(tokenRowFor($tenantB->id, 'budi@example.com'))->toBeNull();

    // Present it on tenant B's host: refused (no row for B).
    post('http://sekolah-b.localhost/reset-password', [
        'token' => $token,
        'email' => 'budi@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('email');

    // Tenant B's user still has the ORIGINAL password.
    expect(app('hash')->check('password123', $userB->refresh()->password))->toBeTrue();
});

it('resets the password of the right user and logs them in', function () {
    [$tenantA, $userA] = resetTenantUser('sekolah-a', 'budi@example.com');
    resetTenantUser('sekolah-b', 'budi@example.com'); // same email elsewhere must stay untouched

    $token = app(TenantContext::class)->run(
        $tenantA->id,
        fn (): string => PasswordBrokerToken::create($userA),
    );

    post('http://sekolah-a.localhost/reset-password', [
        'token' => $token,
        'email' => 'budi@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('home'));

    expect(app('hash')->check('new-password-123', $userA->refresh()->password))->toBeTrue()
        ->and(auth()->user()?->is($userA))->toBeTrue();
});

it('refuses expired tokens', function () {
    [$tenant, $user] = resetTenantUser('sekolah-a', 'budi@example.com');

    $token = app(TenantContext::class)->run(
        $tenant->id,
        fn (): string => PasswordBrokerToken::create($user),
    );

    // Age the row past the configured expiry (60 min).
    DB::table('password_reset_tokens')
        ->where('tenant_id', $tenant->id)
        ->where('email', 'budi@example.com')
        ->update(['created_at' => now()->subHours(2)]);

    post('http://sekolah-a.localhost/reset-password', [
        'token' => $token,
        'email' => 'budi@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('email');

    expect(app('hash')->check('password123', $user->refresh()->password))->toBeTrue();
});

it('responds generically and sends NO email for an unknown address', function () {
    Mail::fake();

    resetTenantUser('sekolah-a', 'someone-else@example.com');

    from('http://sekolah-a.localhost/forgot-password')
        ->post('http://sekolah-a.localhost/forgot-password', [
            'email' => 'ghost@example.com',
        ])->assertRedirect()
        ->assertSessionHas('status'); // same generic status

    Mail::assertNothingQueued();
});

it('responds generically and sends NO email for a deactivated user', function () {
    Mail::fake();

    [$tenant, $user] = resetTenantUser('sekolah-a', 'budi@example.com');

    $user->forceFill(['deactivated_at' => now()])->save();

    from('http://sekolah-a.localhost/forgot-password')
        ->post('http://sekolah-a.localhost/forgot-password', [
            'email' => 'budi@example.com',
        ])->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertNothingQueued();
});

it('responds generically and sends NO email for a user without a password', function () {
    Mail::fake();

    [$tenant, $user] = resetTenantUser('sekolah-a', 'invited@example.com');

    $user->forceFill(['password' => null])->save();

    from('http://sekolah-a.localhost/forgot-password')
        ->post('http://sekolah-a.localhost/forgot-password', [
            'email' => 'invited@example.com',
        ])->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertNothingQueued();
});

it('refuses reset requests without tenant context (central host)', function () {
    resetTenantUser('sekolah-a', 'budi@example.com');

    post('http://localhost/forgot-password', [
        'email' => 'budi@example.com',
    ])->assertSessionHasErrors('email');
});

/**
 * Tiny seam over the password broker for minting tokens in tests
 * (lives here so the suite needs no extra support class file).
 */
class PasswordBrokerToken
{
    public static function create(User $user): string
    {
        /** @var PasswordBroker $broker */
        $broker = app('auth.password.broker');

        $repository = new \ReflectionProperty($broker, 'tokens');
        $repository->setAccessible(true);

        /** @var TokenRepositoryInterface $tokens */
        $tokens = $repository->getValue($broker);

        return $tokens->create($user);
    }
}
