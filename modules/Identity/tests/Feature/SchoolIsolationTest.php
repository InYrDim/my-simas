<?php

namespace Modules\Identity\Tests\Feature;

use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Two schools share one host and clean URLs (/beranda, /users): the
 * school lives in the session, set by the school code at login. These
 * tests prove staff of school A and school B never mix.
 */
function isoSchool(string $slug): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug, 'name' => 'Sekolah '.strtoupper($slug)]);

    app(ModuleFlagManager::class)->enable($tenant->id, 'identity');

    return $tenant;
}

function isoUser(Tenant $tenant, string $email, string $role): User
{
    $user = User::factory()->forTenant($tenant->id)->create([
        'email' => $email,
        'password' => 'SandiRahasia1!',
    ]);

    app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole($role));

    return $user;
}

/** Log in through the real form with the school code. */
function isoLogin(Tenant $tenant, string $email): void
{
    post('/login', [
        'school' => $tenant->id,
        'login' => $email,
        'password' => 'SandiRahasia1!',
    ])->assertRedirect();
}

/** A fresh browser: no cookie, no remembered guard state. */
function isoNewBrowser(): void
{
    test()->flushSession();
    auth()->forgetGuards();
}

it('lands the same email on its own school with a clean /beranda URL', function () {
    $a = isoSchool('iso-a');
    $b = isoSchool('iso-b');
    isoUser($a, 'kepala@sekolah.test', 'admin-sekolah');
    isoUser($b, 'kepala@sekolah.test', 'guru');

    isoLogin($a, 'kepala@sekolah.test');
    get('/beranda')->assertOk()->assertInertia(fn ($page) => $page
        ->where('school.name', 'Sekolah ISO-A')
        ->where('can.invite', true));

    isoNewBrowser();

    isoLogin($b, 'kepala@sekolah.test');
    get('/beranda')->assertOk()->assertInertia(fn ($page) => $page
        ->where('school.name', 'Sekolah ISO-B')
        ->where('can.invite', false));
});

it('never lists another school\'s accounts under /users', function () {
    $a = isoSchool('iso-a');
    $b = isoSchool('iso-b');
    isoUser($a, 'admin@iso-a.test', 'admin-sekolah');
    isoUser($a, 'guru@iso-a.test', 'guru');
    $outsider = isoUser($b, 'guru@iso-b.test', 'guru');

    isoLogin($a, 'admin@iso-a.test');

    get('/users')
        ->assertOk()
        ->assertSee('guru@iso-a.test', false)
        ->assertDontSee('guru@iso-b.test', false);

    get('/users/'.$outsider->id.'/edit')->assertNotFound();
});

it('keeps a logged-in user on their school whatever school code is sent', function () {
    $a = isoSchool('iso-a');
    $b = isoSchool('iso-b');
    isoUser($a, 'admin@iso-a.test', 'admin-sekolah');
    isoUser($b, 'admin@iso-b.test', 'admin-sekolah');

    isoLogin($a, 'admin@iso-a.test');

    get('/beranda?school='.$b->id)->assertInertia(fn ($page) => $page->where('school.name', 'Sekolah ISO-A'));
    post('/users/invite', ['school' => $b->id])->assertSessionHas('tenant_id', $a->id);
});

it('does not let a stolen user id ride on another school\'s session', function () {
    $a = isoSchool('iso-a');
    $b = isoSchool('iso-b');
    $adminA = isoUser($a, 'admin@iso-a.test', 'admin-sekolah');

    // Session claims school B but carries school A's user id.
    $this->withSession([
        'tenant_id' => $b->id,
        auth()->guard('web')->getName() => $adminA->id,
    ]);

    get('/beranda')->assertRedirect(route('login'));
});

it('ends the session when the school disappears mid-session', function () {
    $a = isoSchool('iso-a');
    isoUser($a, 'admin@iso-a.test', 'admin-sekolah');

    isoLogin($a, 'admin@iso-a.test');
    get('/beranda')->assertOk();

    Tenant::query()->whereKey($a->id)->delete();
    cache()->flush();

    get('/beranda')->assertRedirect(route('login'));
    get('/beranda')->assertRedirect(route('login'))->assertSessionMissing('tenant_id');
});

it('blocks a logged-in session when its school gets suspended', function () {
    $a = isoSchool('iso-a');
    isoUser($a, 'admin@iso-a.test', 'admin-sekolah');

    isoLogin($a, 'admin@iso-a.test');

    Tenant::query()->whereKey($a->id)->update(['status' => 'suspended']);
    cache()->flush();

    get('/beranda')->assertForbidden();
});

it('sends logout back to the school login', function () {
    $a = isoSchool('iso-a');
    isoUser($a, 'admin@iso-a.test', 'admin-sekolah');

    isoLogin($a, 'admin@iso-a.test');

    post('/logout')->assertRedirect(route('login'));

    get('/beranda')->assertRedirect(route('login'));
});
