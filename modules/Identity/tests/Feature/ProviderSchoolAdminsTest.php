<?php

namespace Modules\Identity\Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Infrastructure\Mail\ResetPasswordMail;
use Modules\Identity\App\Infrastructure\Mail\SetPasswordMail;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Provider console → Pengguna: school admins across tenants, managed from
 * the console host with the provider guard.
 */
beforeEach(function () {
    // Spatie caches roles per process; see DeactivationTest for why.
    User::flushTenantPermissionCache();
});

function adminsConsole(string $path = ''): string
{
    return 'http://console.localhost/users'.$path;
}

function signInAsProvider(): void
{
    actingAs(ProviderUserFactory::new()->create(), 'provider');
}

/**
 * A user inside the tenant; $role null = no role, $password null = invited.
 */
function schoolUser(Tenant $tenant, string $email, ?string $role = 'admin-sekolah', ?string $password = 'password123'): User
{
    $user = User::factory()->forTenant($tenant->id)->create([
        'email' => $email,
        'name' => ucfirst(strtok($email, '@')),
        'password' => $password,
    ]);

    if ($role !== null) {
        app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole($role));
    }

    return $user;
}

it('keeps the school admin list behind the provider guard', function () {
    $tenant = TenantFactory::new()->create();
    $user = schoolUser($tenant, 'admin@a.test');

    get(adminsConsole())->assertRedirect();

    actingAs($user)->get(adminsConsole())->assertRedirect();
    post(adminsConsole("/{$tenant->id}/{$user->id}/deactivate"))->assertRedirect();

    expect($user->refresh()->deactivated_at)->toBeNull();
});

it('lists only school admins across tenants with their status', function () {
    signInAsProvider();
    $a = TenantFactory::new()->create(['name' => 'Sekolah A']);
    $b = TenantFactory::new()->create(['name' => 'Sekolah B']);

    schoolUser($a, 'admin@a.test');
    schoolUser($a, 'guru@a.test', 'guru');
    schoolUser($b, 'invited@b.test', 'admin-sekolah', null);
    $off = schoolUser($b, 'off@b.test');
    $off->forceFill(['deactivated_at' => now()])->save();

    get(adminsConsole())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Identity/Console/SchoolAdmins/Index')
            ->where('admins.total', 3)
            ->where('admins.data.0.email', 'admin@a.test')
            ->where('admins.data.0.status', 'active')
            ->where('admins.data.1.status', 'invited')
            ->where('admins.data.2.status', 'deactivated'));

    get(adminsConsole("?tenant={$a->id}"))
        ->assertInertia(fn (Assert $page) => $page->where('admins.total', 1));

    get(adminsConsole('?status=invited'))
        ->assertInertia(fn (Assert $page) => $page->where('admins.total', 1));

    get(adminsConsole('?q=off@'))
        ->assertInertia(fn (Assert $page) => $page->where('admins.total', 1));
});

it('invites a school admin into a chosen tenant', function () {
    Mail::fake();
    signInAsProvider();
    $tenant = TenantFactory::new()->create();

    post(adminsConsole(), [
        'tenant_id' => $tenant->id,
        'name' => 'Budi Santoso',
        'email' => 'Budi@Sekolah.test',
    ])->assertSessionHasNoErrors();

    $user = app(TenantContext::class)->run($tenant->id, fn () => User::query()->where('email', 'budi@sekolah.test')->first());

    expect($user)->not->toBeNull()
        ->and($user->password)->toBeNull()
        ->and(app(TenantContext::class)->run($tenant->id, fn () => $user->hasTenantRole('admin-sekolah')))->toBeTrue();

    Mail::assertQueued(SetPasswordMail::class);
});

it('refuses to re-invite an account that is already active', function () {
    Mail::fake();
    signInAsProvider();
    $tenant = TenantFactory::new()->create();
    schoolUser($tenant, 'admin@a.test');

    post(adminsConsole(), ['tenant_id' => $tenant->id, 'name' => 'Admin', 'email' => 'admin@a.test'])
        ->assertSessionHasErrors('billing');

    Mail::assertNothingQueued();
});

it('validates the invitation and rejects an unknown tenant', function () {
    signInAsProvider();

    post(adminsConsole(), ['tenant_id' => '', 'name' => '', 'email' => 'bukan-email'])
        ->assertSessionHasErrors(['tenant_id', 'name', 'email']);

    post(adminsConsole(), ['tenant_id' => 'unknown', 'name' => 'X', 'email' => 'x@y.test'])
        ->assertNotFound();
});

it('sends a reset link to an active admin only', function () {
    Mail::fake();
    signInAsProvider();
    $tenant = TenantFactory::new()->create();
    $active = schoolUser($tenant, 'admin@a.test');
    $invited = schoolUser($tenant, 'invited@a.test', 'admin-sekolah', null);

    post(adminsConsole("/{$tenant->id}/{$active->id}/reset"))->assertSessionHasNoErrors();
    Mail::assertQueued(ResetPasswordMail::class);

    post(adminsConsole("/{$tenant->id}/{$invited->id}/reset"))->assertSessionHasErrors('billing');
});

it('resends the invitation to a not-yet-activated admin', function () {
    Mail::fake();
    signInAsProvider();
    $tenant = TenantFactory::new()->create();
    $invited = schoolUser($tenant, 'invited@a.test', 'admin-sekolah', null);

    post(adminsConsole("/{$tenant->id}/{$invited->id}/invite"))->assertSessionHasNoErrors();

    Mail::assertQueued(SetPasswordMail::class);
});

it('deactivates and reactivates an admin but protects the last active one', function () {
    signInAsProvider();
    $tenant = TenantFactory::new()->create();
    $first = schoolUser($tenant, 'one@a.test');
    $second = schoolUser($tenant, 'two@a.test');

    post(adminsConsole("/{$tenant->id}/{$first->id}/deactivate"))->assertSessionHasNoErrors();
    expect($first->refresh()->deactivated_at)->not->toBeNull();

    post(adminsConsole("/{$tenant->id}/{$second->id}/deactivate"))->assertSessionHasErrors('billing');
    expect($second->refresh()->deactivated_at)->toBeNull();

    post(adminsConsole("/{$tenant->id}/{$first->id}/reactivate"))->assertSessionHasNoErrors();
    expect($first->refresh()->deactivated_at)->toBeNull();
});

it('does not manage users who are not school admins or belong to another tenant', function () {
    signInAsProvider();
    $a = TenantFactory::new()->create();
    $b = TenantFactory::new()->create();
    $teacher = schoolUser($a, 'guru@a.test', 'guru');
    $admin = schoolUser($a, 'admin@a.test');

    post(adminsConsole("/{$a->id}/{$teacher->id}/deactivate"))->assertNotFound();
    post(adminsConsole("/{$b->id}/{$admin->id}/deactivate"))->assertNotFound();
    post(adminsConsole("/missing/{$admin->id}/deactivate"))->assertNotFound();
});
