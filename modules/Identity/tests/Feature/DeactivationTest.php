<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Identity\App\Domain\Actions\DeactivateUser;
use Modules\Identity\App\Domain\Actions\ReactivateUser;
use Modules\Identity\App\Domain\Exceptions\DeactivationNotAllowedException;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Stage 3 (Fase 2): deactivation enforcement.
 *
 * - deactivated users cannot log in (generic error, no enumeration)
 * - live sessions are ended by middleware attribute enforcement
 * - anti-lockout invariants: no self-deactivation, no removing the
 *   last active school admin
 * - reactivation restores access (roles were kept)
 */
beforeEach(function () {
    // Minimal Inertia-free probe route to inspect the session user.
    Route::middleware('web')->get('/auth-probe', function (): string {
        $user = auth()->user();

        return 'user:'.($user->email ?? 'guest');
    });

    // Spatie caches permissions/roles globally (cache array store per
    // process) — a stale entry from a previous test in the same process
    // makes role lookups resolve against the WRONG tenant's pivot ids.
    // Flush via Platform's public wrapper (no Spatie import here).
    User::flushTenantPermissionCache();
});

/**
 * Create a tenant + a login-able user for it. Reuses an existing
 * tenant with the same slug (tests create several users per school).
 *
 * @return array{0: string, 1: User}
 */
function deactivationTenantUser(string $slug, string $email, ?string $role = null): array
{
    $tenant = Tenant::query()->where('slug', $slug)->first()
        ?? TenantFactory::new()->create(['slug' => $slug]);

    $user = User::factory()->forTenant($tenant->id)->create([
        'email' => $email,
        'password' => 'password123',
    ]);

    if ($role !== null) {
        app(TenantContext::class)->run($tenant->id, function () use ($user, $role): void {
            $user->assignTenantRole($role);
        });
    }

    return [$tenant->id, $user];
}

/**
 * The acting admin (first seeded admin of the tenant) for action tests.
 *
 * @return array{0: Tenant, 1: User}
 */
function deactivationAdminTenant(string $slug): array
{
    $tenant = Tenant::query()->where('slug', $slug)->first()
        ?? TenantFactory::new()->create(['slug' => $slug]);

    $admin = User::factory()->forTenant($tenant->id)->create([
        'email' => 'the-admin@'.str_replace('-', '', $slug).'.test',
        'password' => 'password123',
    ]);

    app(TenantContext::class)->run($tenant->id, function () use ($admin): void {
        $admin->assignTenantRole('admin-sekolah');
    });

    return [$tenant, $admin];
}

it('refuses login for a deactivated user with the generic error', function () {
    [$tenantId, $user] = deactivationTenantUser('sekolah-a', 'budi@example.com');

    $user->forceFill(['deactivated_at' => now()])->save();

    from(school('sekolah-a', '/login'))
        ->post(school('sekolah-a', '/login'), [
            'school' => schoolId('sekolah-a'),
            'email' => 'budi@example.com',
            'password' => 'password123',
        ])->assertSessionHasErrors('email');

    // Generic message — indistinguishable from a wrong password.
    expect(session('errors')->get('email')[0])->toBe(__('auth.failed'))
        ->and(auth()->user())->toBeNull();
});

it('ends the live session of a user deactivated mid-session', function () {
    [$tenantId, $user] = deactivationTenantUser('sekolah-a', 'budi@example.com');

    actingAs($user);

    // Session is alive before deactivation.
    $before = get(school('sekolah-a', '/auth-probe'));
    expect($before->getContent())->toContain('budi@example.com');

    $user->forceFill(['deactivated_at' => now()])->save();

    // EnsureSessionTenant inspects the attribute on the NEXT request.
    $after = get(school('sekolah-a', '/auth-probe'));

    expect($after->getContent())->not->toContain('budi@example.com')
        ->and(auth()->user())->toBeNull();
});

it('does not deactivate the tenant identity across tenants', function () {
    // Deactivation is a per-tenant fact: deactivating budi in
    // sekolah-a must not affect budi in sekolah-b (same email).
    [$aId, $userA] = deactivationTenantUser('sekolah-a', 'budi@example.com');
    [, $userB] = deactivationTenantUser('sekolah-b', 'budi@example.com');

    $userA->forceFill(['deactivated_at' => now()])->save();

    // Tenant B's budi still logs in on its own host.
    post(school('sekolah-b', '/login'), [
        'school' => schoolId('sekolah-b'),
        'email' => 'budi@example.com',
        'password' => 'password123',
    ])->assertRedirect();

    expect(auth()->user()?->is($userB))->toBeTrue()
        ->and($userB->deactivated_at)->toBeNull();
});

it('rejects self-deactivation', function () {
    [, $admin] = deactivationAdminTenant('sekolah-a');

    app(DeactivateUser::class)->handle($admin, $admin);
})->throws(DeactivationNotAllowedException::class);

it('rejects deactivating the last active school admin', function () {
    [, $admin] = deactivationAdminTenant('sekolah-a');
    [, $guru] = deactivationTenantUser('sekolah-a', 'guru@example.com', 'guru');

    // The tenant's only ACTIVE admin cannot be removed by anyone.
    app(DeactivateUser::class)->handle($guru, $admin);
})->throws(DeactivationNotAllowedException::class);

it('does not count deactivated admins as guardians', function () {
    [, $firstAdmin] = deactivationAdminTenant('sekolah-a');
    [, $secondAdmin] = deactivationTenantUser('sekolah-a', 'admin2@example.com', 'admin-sekolah');

    // First admin deactivates the second — allowed (one admin remains).
    app(DeactivateUser::class)->handle($firstAdmin, $secondAdmin);

    expect($secondAdmin->refresh()->deactivated_at)->not->toBeNull();

    // Now the first admin IS the last active one; even they cannot be
    // removed (the invariant checks the CURRENT active count, not the
    // target's goodwill).
    app(DeactivateUser::class)->handle($firstAdmin, $firstAdmin);
})->throws(DeactivationNotAllowedException::class);

it('allows deactivating a non-admin user', function () {
    [$tenant, $admin] = deactivationAdminTenant('sekolah-a');
    [, $guru] = deactivationTenantUser('sekolah-a', 'guru@example.com', 'guru');

    app(DeactivateUser::class)->handle($admin, $guru);

    expect($guru->refresh()->deactivated_at)->not->toBeNull();

    // Roles are kept for reactivation. Assert at the PIVOT level:
    // Spatie caches loaded relations on the model instance, so a
    // stale instance can misreport role names — the pivot row is the
    // ground truth (and the same reason the login path must not trust
    // stale role caches for authorization-critical decisions).
    $roleId = DB::table('roles')
        ->where('tenant_id', $tenant->id)
        ->where('name', 'guru')
        ->value('id');

    expect(DB::table('model_has_roles')
        ->where('role_id', $roleId)
        ->where('model_id', $guru->id)
        ->where('model_type', User::class)
        ->where('tenant_id', $tenant->id)
        ->exists())->toBeTrue();
});

it('restores access on reactivation', function () {
    [$tenantId, $user] = deactivationTenantUser('sekolah-a', 'budi@example.com');

    $user->forceFill(['deactivated_at' => now()])->save();

    app(ReactivateUser::class)->handle($user);

    expect($user->refresh()->deactivated_at)->toBeNull();

    post(school('sekolah-a', '/login'), [
        'school' => schoolId('sekolah-a'),
        'email' => 'budi@example.com',
        'password' => 'password123',
    ])->assertRedirect();

    expect(auth()->user()?->is($user))->toBeTrue();
});

it('deactivated users are invisible to cross-tenant login isolation', function () {
    // Sanity on isolation semantics: a deactivated account in tenant A
    // says nothing about tenant B's same-email account (already covered
    // above); this test pins the resolver behaviour from the plan.
    [$aId, $userA] = deactivationTenantUser('sekolah-a', 'budi@example.com');
    [, $userB] = deactivationTenantUser('sekolah-b', 'budi@example.com');

    $userA->forceFill(['deactivated_at' => now()])->save();

    $record = app(TenantContext::class)->run(
        $aId,
        fn () => app(ResolvesUsers::class)->findByEmail('budi@example.com'),
    );

    // Resolver finds the row (visibility), login still refuses it.
    expect($record)->not->toBeNull()
        ->and($userB->deactivated_at)->toBeNull();
});
