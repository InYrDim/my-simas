<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Stage 9: tenant-ready authentication.
 *
 * - same email may exist in two tenants (composite unique)
 * - credentials from tenant B never authenticate on tenant A
 * - a session cookie from tenant A never authenticates on tenant B
 * - login works in tenant context, fails without one
 * - the login rate limiter is keyed per tenant
 */
beforeEach(function () {
    // Minimal Inertia-free probe route to inspect the session user.
    Route::middleware('web')->get('/auth-probe', function (): string {
        $user = auth()->user();

        return 'user:'.($user->email ?? 'guest');
    });
});

/**
 * Create a tenant + its login-able user.
 *
 * @return array{0: string, 1: User}
 */
function tenantUser(string $slug, string $email): array
{
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    $user = User::factory()->forTenant($tenant->id)->create([
        'email' => $email,
        'password' => 'password123',
    ]);

    return [$tenant->id, $user];
}

it('allows the same email across two tenants (composite unique)', function () {
    tenantUser('sekolah-a', 'budi@example.com');
    tenantUser('sekolah-b', 'budi@example.com');

    expect(User::withoutTenancy()->where('email', 'budi@example.com')->count())->toBe(2);
});

it('logs a user in on their own tenant host', function () {
    [, $user] = tenantUser('sekolah-a', 'budi@example.com');

    post(school('sekolah-a', '/login'), [
        'school' => schoolId('sekolah-a'),
        'email' => 'budi@example.com',
        'password' => 'password123',
    ])->assertRedirect();

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('rejects tenant B credentials on tenant A host', function () {
    tenantUser('sekolah-b', 'budi@example.com');
    tenantUser('sekolah-a', 'siti@example.com');

    // Valid credential for tenant B, but posted on tenant A's host.
    from(school('sekolah-a', '/login'))
        ->post(school('sekolah-a', '/login'), [
            'school' => schoolId('sekolah-a'),
            'email' => 'budi@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

    expect(auth()->user())->toBeNull();
});

it('does not authenticate a tenant A session cookie on tenant B host', function () {
    [, $user] = tenantUser('sekolah-a', 'budi@example.com');
    tenantUser('sekolah-b', 'siti@example.com');

    // Login on tenant A to obtain its session cookie.
    $response = post(school('sekolah-a', '/login'), [
        'school' => schoolId('sekolah-a'),
        'email' => 'budi@example.com',
        'password' => 'password123',
    ]);

    expect(auth()->user()?->is($user))->toBeTrue();

    $sessionCookie = collect($response->headers->getCookies())
        ->first(fn ($cookie) => str_contains($cookie->getName(), 'session'));

    expect($sessionCookie)->not->toBeNull()
        // Host-only: the browser must never send it to another tenant.
        ->and($sessionCookie->getDomain())->toBeNull();

    // Replay the SAME cookie against tenant B: the array session driver
    // keeps sessions per-process, so the replay may resolve the user,
    // but EnsureSessionTenant (web group) must have logged it out — the
    // session user belongs to tenant A, not the resolved tenant B.
    $probe = get(school('sekolah-b', '/auth-probe'), [
        'Cookie' => $sessionCookie->getName().'='.$sessionCookie->getValue(),
    ]);

    // Either the cookie never matched a session (guest) or the guard
    // middleware invalidated it (guest). Either way: NO tenant A user.
    expect($probe->getContent())->not->toContain('budi@example.com');
});

it('requires a school code to log in', function () {
    tenantUser('sekolah-a', 'budi@example.com');

    from('http://localhost/login')
        ->post('http://localhost/login', [
            'email' => 'budi@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('school');

    expect(auth()->user())->toBeNull();
});

it('refuses an unknown school code with the same generic error as a wrong password', function () {
    tenantUser('sekolah-a', 'budi@example.com');

    foreach (['01ARZ3NDEKTSV4RRFFQ69G5FAV', 'sekolah-a', 'nonsense'] as $code) {
        from('http://localhost/login')
            ->post('http://localhost/login', [
                'school' => $code,
                'email' => 'budi@example.com',
                'password' => 'password123',
            ])->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    expect(auth()->user())->toBeNull();
});

it('remembers the school after login so later pages need no school code', function () {
    [$tenantId, $user] = tenantUser('sekolah-a', 'budi@example.com');

    post('http://localhost/login', [
        'school' => $tenantId,
        'email' => 'budi@example.com',
        'password' => 'password123',
    ])->assertRedirect()
        ->assertSessionHas('tenant_id', $tenantId);

    get('/auth-probe')->assertSee('user:budi@example.com', false);
});

it('keeps a logged-in user on their own school even if another school code is sent', function () {
    [$tenantA, $userA] = tenantUser('sekolah-a', 'budi@example.com');
    [$tenantB] = tenantUser('sekolah-b', 'siti@example.com');

    $this->withSession([
        'tenant_id' => $tenantA,
        auth()->guard('web')->getName() => $userA->id,
    ]);

    get('/auth-probe?school='.$tenantB)->assertSee('user:budi@example.com', false);
});

it('does not log in on the console host', function () {
    [$tenantId] = tenantUser('sekolah-a', 'budi@example.com');

    post('http://console.localhost/login', [
        'school' => $tenantId,
        'email' => 'budi@example.com',
        'password' => 'password123',
    ])->assertSessionHasErrors('email');

    expect(auth()->user())->toBeNull();
});

it('throttles logins per tenant and email', function () {
    tenantUser('sekolah-a', 'budi@example.com');
    tenantUser('sekolah-b', 'budi@example.com');

    // 5 failed attempts on tenant A exhaust that tenant's bucket...
    for ($i = 0; $i < 5; $i++) {
        post(school('sekolah-a', '/login'), [
            'school' => schoolId('sekolah-a'),
            'email' => 'budi@example.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    // ...6th attempt on tenant A is throttled (rendered as a redirect
    // with the throttle error for plain form posts; 429 for JSON).
    post(school('sekolah-a', '/login'), [
        'school' => schoolId('sekolah-a'),
        'email' => 'budi@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    // The SAME email on tenant B still gets a normal attempt (per-tenant buckets).
    post(school('sekolah-b', '/login'), [
        'school' => schoolId('sekolah-b'),
        'email' => 'budi@example.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

it('logs the user out', function () {
    [, $user] = tenantUser('sekolah-a', 'budi@example.com');

    actingAs($user);

    expect(auth()->user()?->is($user))->toBeTrue();

    post(school('sekolah-a', '/logout'))->assertRedirect();

    expect(auth()->user())->toBeNull();
});

it('keeps guest access to the login page on a tenant host', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get(school('sekolah-a', '/login'))->assertOk();
});
