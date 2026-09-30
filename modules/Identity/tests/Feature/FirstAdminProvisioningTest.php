<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Infrastructure\Mail\SetPasswordMail;
use Modules\Identity\App\Infrastructure\Onboarding\ProvisionFirstAdmin;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\Events\TenantApproved;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Stage 8 (Fase 2): first school admin provisioning + set-password
 * acceptance. Platform approves an application → TenantApproved fires
 * inside its transaction → Identity provisions the admin (password
 * null, role admin-sekolah) and queues a set-password mail whose link
 * points at the TENANT host. The acceptance page shares the tenant-
 * scoped token machinery with reset; the page decides the effect.
 */
function dispatchApproved(string $tenantId, string $name = 'Kepsek Baru', string $email = 'kepsek@baru.test'): void
{
    Event::dispatch(new TenantApproved($tenantId, $name, $email));
}

function mintedTokenRow(string $tenantId, string $email): ?object
{
    return DB::table('password_reset_tokens')
        ->where('tenant_id', $tenantId)
        ->where('email', $email)
        ->first();
}

it('provisions the first admin pinned to the approved tenant with the right role', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'baru-a']);

    dispatchApproved($tenant->id);

    // The SAME email may exist in other tenants — this row must be
    // the one created for THIS tenant.
    $user = DB::table('users')
        ->where('email', 'kepsek@baru.test')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($user)->not->toBeNull()
        ->and($user->password)->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        // Role assigned (machine name) — checked via Spatie tables.
        ->and(
            DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('roles.name', 'admin-sekolah')
                ->where('roles.tenant_id', $tenant->id)
                ->where('model_has_roles.model_id', $user->id)
                ->exists(),
        )->toBeTrue();
});

it('queues a set-password mail with a tenant-hosted link', function () {
    Mail::fake();

    $tenant = TenantFactory::new()->create(['slug' => 'baru-b']);

    dispatchApproved($tenant->id);

    Mail::assertQueued(SetPasswordMail::class, function (SetPasswordMail $mail): bool {
        return str_contains($mail->setPasswordUrl, 'baru-b.localhost')
            && str_contains($mail->setPasswordUrl, '/set-password?token=');
    });
});

it('does not duplicate the admin on re-approval and keeps activation intact', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'baru-c']);

    dispatchApproved($tenant->id);
    dispatchApproved($tenant->id);

    $count = DB::table('users')
        ->where('email', 'kepsek@baru.test')
        ->where('tenant_id', $tenant->id)
        ->count();

    expect($count)->toBe(1);

    // An ACTIVATED account is never reset by a re-run.
    DB::table('users')
        ->where('email', 'kepsek@baru.test')
        ->where('tenant_id', $tenant->id)
        ->update(['password' => 'activated-hash', 'email_verified_at' => now()]);

    dispatchApproved($tenant->id);

    $user = DB::table('users')
        ->where('email', 'kepsek@baru.test')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($user->password)->toBe('activated-hash');
});

function mintFreshToken(object $tenant, string $email): string
{
    // Mint a fresh plain token for the EXISTING provisioned user (the
    // listener's own token is hashed at rest and unrecoverable by
    // design). Ambient context decides the tenant, same as the listener.
    $user = User::withoutTenancy()
        ->where('email', $email)
        ->where('tenant_id', $tenant->id)
        ->firstOrFail();

    return app(TenantContext::class)->run(
        $tenant->id,
        function () use ($user): string {
            $broker = App::make('auth.password.broker');

            $repositoryProp = new \ReflectionProperty($broker, 'tokens');
            $repositoryProp->setAccessible(true);

            /** @var TokenRepositoryInterface $tokens */
            $tokens = $repositoryProp->getValue($broker);

            return $tokens->create($user);
        },
    );
}

it('accepts the emailed token: password set, email verified, auto-login', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'baru-d']);

    dispatchApproved($tenant->id, 'Kepsek D', 'kepsek@baru-d.test');

    $tokenRow = mintedTokenRow($tenant->id, 'kepsek@baru-d.test');
    expect($tokenRow)->not->toBeNull();

    $plainToken = mintFreshToken($tenant, 'kepsek@baru-d.test');

    // The fresh mint replaces the listener's row (deleteExisting).
    $user = DB::table('users')
        ->where('email', 'kepsek@baru-d.test')
        ->where('tenant_id', $tenant->id)
        ->first();

    post('http://baru-d.localhost/set-password', [
        'token' => $plainToken,
        'email' => 'kepsek@baru-d.test',
        'password' => 'SandiBaruKuat123!',
        'password_confirmation' => 'SandiBaruKuat123!',
    ])
        ->assertRedirect(route('home'));

    $row = DB::table('users')->find($user->id);

    expect($row->password)->not->toBeNull()
        ->and($row->password)->not->toBe('activated-hash')
        ->and($row->email_verified_at)->not->toBeNull()
        // Auto-logged-in on their own host (web guard).
        ->and(auth()->check())->toBeTrue()
        // Token consumed: one-shot.
        ->and(mintedTokenRow($tenant->id, 'kepsek@baru-d.test'))->toBeNull();
});

it('refuses activation for a deactivated account', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'baru-e']);

    dispatchApproved($tenant->id, 'Kepsek E', 'kepsek@baru-e.test');

    $user = DB::table('users')
        ->where('email', 'kepsek@baru-e.test')
        ->where('tenant_id', $tenant->id)
        ->first();

    // Admin deactivated the account before it was ever activated.
    DB::table('users')->where('id', $user->id)
        ->update(['deactivated_at' => now()]);

    $plainToken = mintFreshToken($tenant, 'kepsek@baru-e.test');

    post('http://baru-e.localhost/set-password', [
        'token' => $plainToken,
        'email' => 'kepsek@baru-e.test',
        'password' => 'SandiBaruKuat123!',
        'password_confirmation' => 'SandiBaruKuat123!',
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    // Still no password: refused.
    $row = DB::table('users')->find($user->id);

    expect($row->password)->toBeNull()
        ->and($row->email_verified_at)->toBeNull();
});

it('serves the set-password form only on the tenant host', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'baru-f']);

    get('http://baru-f.localhost/set-password?token=x&email=a@b.test')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/Auth/SetPassword')
            ->where('email', 'a@b.test')
            ->where('token', 'x'));
});

it('listener is registered and wired to the TenantApproved event', function () {
    $listeners = Event::getListeners(TenantApproved::class);

    expect($listeners)->not->toBeEmpty();

    // Class listeners are wrapped in a Dispatcher closure carrying the
    // class name in its static "listener" variable — unwrap to check.
    $wired = collect($listeners)->contains(function ($listener): bool {
        if (! $listener instanceof \Closure) {
            return false;
        }

        $static = (new \ReflectionFunction($listener))->getStaticVariables();

        return is_string($static['listener'] ?? null)
            && $static['listener'] === ProvisionFirstAdmin::class;
    });

    expect($wired)->toBeTrue();
});

it('mints the token through the broker seam used by the listener', function () {
    // Guards the reflection seam: if the broker's repository property
    // moves, provisioning fails loudly HERE instead of at runtime.
    $tenant = TenantFactory::new()->create(['slug' => 'baru-g']);

    UserFactory::new()->forTenant($tenant->id)->create(['email' => 'seam@baru-g.test']);

    $plainToken = mintFreshToken($tenant, 'seam@baru-g.test');

    expect($plainToken)->not->toBe('')
        ->and(mintedTokenRow($tenant->id, 'seam@baru-g.test'))->not->toBeNull();
});
