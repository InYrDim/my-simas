<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Infrastructure\Mail\ResetPasswordMail;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Stage 9 (Fase 2): school-admin user management.
 *
 * - admin sees the list / creates / edits / role sync
 * - guru & staf are 403 on everything
 * - cross-tenant ids 404 (scope) even for a permitted admin
 * - same email in another tenant is fine (unique komposit)
 * - deactivate via UI closes access (Stage 3 traversal)
 * - anti-lockout flags & refusals surface through the UI
 * - send reset link reuses the Stage 4 machinery (queued mail,
 *   tenant-host URL)
 * - routes live behind the identity module flag (inactive = 403)
 */
function umTenant(string $slug): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    // User management lives behind the identity module flag.
    app(ModuleFlagManager::class)->enable($tenant->id, 'identity');

    return $tenant;
}

function umUser(
    Tenant $tenant,
    string $email,
    ?string $role = null,
    string $password = 'SandiRahasia1!',
): User {
    $user = User::factory()->forTenant($tenant->id)->create([
        'email' => $email,
        'password' => $password,
    ]);

    if ($role !== null) {
        app(TenantContext::class)->run($tenant->id, function () use ($user, $role): void {
            $user->assignTenantRole($role);
        });
    }

    return $user;
}

beforeEach(function () {
    // Spatie caches permissions per process — stale entries from a
    // previous test would mis-resolve tenant pivots.
    User::flushTenantPermissionCache();
});

it('lets the school admin view the user list', function () {
    $tenant = umTenant('um-a');
    $admin = umUser($tenant, 'admin@um-a.test', 'admin-sekolah');
    umUser($tenant, 'guru@um-a.test', 'guru');

    actingAs($admin)
        ->get(school('um-a', '/users'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/Users/Index')
            ->where('roleLabels.admin-sekolah', 'Admin Sekolah')
            ->etc());
});

it('creates a user from the form: verified, hashed, optional role', function () {
    $tenant = umTenant('um-b');
    $admin = umUser($tenant, 'admin@um-b.test', 'admin-sekolah');

    actingAs($admin)
        ->from(school('um-b', '/users/create'))
        ->post(school('um-b', '/users'), [
            'name' => 'Bu Rina',
            'email' => 'rina@um-b.test',
            'password' => 'SandiRahasia1!',
            'role' => 'guru',
        ])
        ->assertRedirect(school('um-b', '/users'))
        ->assertSessionHas('status');

    $user = app(TenantContext::class)->run(
        $tenant->id,
        fn (): ?User => User::query()->where('email', 'rina@um-b.test')->first(),
    );

    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBe('SandiRahasia1!') // hashed cast
        ->and($user->isActive())->toBeTrue();

    app(TenantContext::class)->run($tenant->id, function () use ($user): void {
        expect($user->refresh()->hasTenantRole('guru'))->toBeTrue();
    });
});

it('rejects duplicate emails within the tenant but allows them across tenants', function () {
    $a = umTenant('um-c-a');
    $b = umTenant('um-c-b');
    $admin = umUser($a, 'admin@um-c-a.test', 'admin-sekolah');
    umUser($b, 'duplikat@shared.test');

    actingAs($admin)
        ->post(school('um-c-a', '/users'), [
            'name' => 'Duplikat',
            'email' => 'admin@um-c-a.test', // taken in THIS tenant
            'password' => 'SandiRahasia1!',
        ])
        ->assertSessionHasErrors('email');

    // The same email exists in tenant B — creating it in tenant A is fine.
    actingAs($admin)
        ->post(school('um-c-a', '/users'), [
            'name' => 'Lain Tenant',
            'email' => 'duplikat@shared.test',
            'password' => 'SandiRahasia1!',
        ])
        ->assertRedirect(school('um-c-a', '/users'))
        ->assertSessionHasNoErrors();
});

it('syncs name and roles from the edit form', function () {
    $tenant = umTenant('um-d');
    $admin = umUser($tenant, 'admin@um-d.test', 'admin-sekolah');
    $guru = umUser($tenant, 'guru@um-d.test', 'guru');

    actingAs($admin)
        ->put(school('um-d', '/users/').$guru->id, [
            'name' => 'Guru Rebrand',
            'roles' => ['staf-tu'],
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($guru->refresh()->name)->toBe('Guru Rebrand');

    app(TenantContext::class)->run($tenant->id, function () use ($guru): void {
        expect($guru->hasTenantRole('guru'))->toBeFalse()
            ->and($guru->hasTenantRole('staf-tu'))->toBeTrue();
    });
});

it('sends the reset link through the Stage 4 machinery', function () {
    Mail::fake();

    $tenant = umTenant('um-e');
    $admin = umUser($tenant, 'admin@um-e.test', 'admin-sekolah');
    $guru = umUser($tenant, 'guru@um-e.test', 'guru');

    actingAs($admin)
        ->post(school('um-e', '/users/').$guru->id.'/send-reset')
        ->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertQueued(
        ResetPasswordMail::class,
        fn (ResetPasswordMail $mail): bool => str_contains($mail->resetUrl, 'school='.$tenant->id),
    );

    // A token row was minted for the TARGET's tenant.
    expect(DB::table('password_reset_tokens')
        ->where('tenant_id', $tenant->id)
        ->where('email', 'guru@um-e.test')
        ->exists())->toBeTrue();
});

it('guru is forbidden on every management route', function () {
    $tenant = umTenant('um-f');
    $guru = umUser($tenant, 'guru@um-f.test', 'guru');
    $other = umUser($tenant, 'lain@um-f.test');

    actingAs($guru);

    get(school('um-f', '/users'))->assertForbidden();
    get(school('um-f', '/users/create'))->assertForbidden();

    post(school('um-f', '/users'), [
        'name' => 'X', 'email' => 'x@um-f.test', 'password' => 'SandiRahasia1!',
    ])->assertForbidden();

    put(school('um-f', '/users/').$other->id, [
        'name' => 'X', 'roles' => [],
    ])->assertForbidden();

    patch(school('um-f', '/users/').$other->id.'/deactivate')->assertForbidden();
    patch(school('um-f', '/users/').$other->id.'/reactivate')->assertForbidden();
    post(school('um-f', '/users/').$other->id.'/send-reset')->assertForbidden();
});

it('cross-tenant ids are 404 even for a permitted admin', function () {
    $a = umTenant('um-g-a');
    $b = umTenant('um-g-b');
    $admin = umUser($a, 'admin@um-g-a.test', 'admin-sekolah');
    $victim = umUser($b, 'korban@um-g-b.test');

    actingAs($admin);

    // The scope hides the id BEFORE the policy ever runs.
    get(school('um-g-a', '/users/').$victim->id.'/edit')->assertNotFound();

    put(school('um-g-a', '/users/').$victim->id, [
        'name' => 'Diubah', 'roles' => [],
    ])->assertNotFound();

    patch(school('um-g-a', '/users/').$victim->id.'/deactivate')->assertNotFound();
    post(school('um-g-a', '/users/').$victim->id.'/send-reset')->assertNotFound();

    expect($victim->refresh()->name)->not->toBe('Diubah')
        ->and($victim->refresh()->deactivated_at)->toBeNull();
});

it('deactivating from the UI closes access on the next login (Stage 3 traversal)', function () {
    $tenant = umTenant('um-h');
    $admin = umUser($tenant, 'admin@um-h.test', 'admin-sekolah');
    $guru = umUser($tenant, 'guru@um-h.test', 'guru');

    actingAs($admin)
        ->patch(school('um-h', '/users/').$guru->id.'/deactivate')
        ->assertRedirect()
        ->assertSessionHas('status');

    expect($guru->refresh()->deactivated_at)->not->toBeNull();

    // The UI session belongs to the ADMIN (guest middleware would
    // bounce an authenticated user off the login route) — end it
    // before the credential attempt.
    auth()->guard('web')->logout();

    // And the login form refuses the account with the generic error.
    from(school('um-h', '/login'))
        ->post(school('um-h', '/login'), [
            'school' => schoolId('um-h'),
            'email' => 'guru@um-h.test',
            'password' => 'SandiRahasia1!',
        ])
        ->assertSessionHasErrors('email');
});

it('shows the anti-lockout flags and refuses the invariants through the UI', function () {
    $tenant = umTenant('um-i');
    $admin = umUser($tenant, 'admin@um-i.test', 'admin-sekolah');

    // Edit page marks isSelf + isLastActiveAdmin for the tenant's only admin.
    actingAs($admin)
        ->get(school('um-i', '/users/').$admin->id.'/edit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Identity/Users/Edit')
            ->where('isSelf', true)
            ->where('isLastActiveAdmin', true));

    // The action refuses deactivating the last active admin anyway.
    actingAs($admin)
        ->patch(school('um-i', '/users/').$admin->id.'/deactivate')
        ->assertSessionHasErrors('user');

    expect($admin->refresh()->deactivated_at)->toBeNull();

    // A second admin can be deactivated by the first (flag flips too).
    $second = umUser($tenant, 'admin2@um-i.test', 'admin-sekolah');

    // Re-acquire the actor: Spatie caches loaded role relations on the
    // model INSTANCE, and this instance already served two requests —
    // production gets a fresh instance per request, so the test does
    // the same (stale relations must never leak across requests).
    $admin = $admin->fresh();

    actingAs($admin)
        ->get(school('um-i', '/users/').$second->id.'/edit')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('isSelf', false)
            ->where('isLastActiveAdmin', false));

    actingAs($admin->fresh())
        ->patch(school('um-i', '/users/').$second->id.'/deactivate')
        ->assertRedirect();

    expect($second->refresh()->deactivated_at)->not->toBeNull();

    // Reactivate restores access.
    actingAs($admin->fresh())
        ->patch(school('um-i', '/users/').$second->id.'/reactivate')
        ->assertRedirect();

    expect($second->refresh()->deactivated_at)->toBeNull();
});

it('routes are 403 when the identity module is inactive', function () {
    // Tenant WITHOUT the identity flag enabled.
    $tenant = TenantFactory::new()->create(['slug' => 'um-j-off']);
    $admin = umUser($tenant, 'admin@um-j-off.test', 'admin-sekolah');

    expect(app(TenantModules::class)->isEnabled('identity', $tenant->id))->toBeFalse();

    actingAs($admin)
        ->get(school('um-j-off', '/users'))
        ->assertForbidden();
});
