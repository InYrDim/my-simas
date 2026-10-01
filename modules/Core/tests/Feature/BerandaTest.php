<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Support\Carbon;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Beranda Sekolah — the landing record. It may only speak from public
 * surfaces: the tenant's own clock, Identity's account counts, and the
 * roles the tenant actually has.
 *
 * Core never imports Identity's User model — accounts are made here
 * through Identity's factory and typed only as Authenticatable.
 */
function berandaTenant(string $slug, string $timezone = 'Asia/Jakarta'): Tenant
{
    return TenantFactory::new()->create(['slug' => $slug, 'timezone' => $timezone]);
}

/** Sign in as a school administrator of that tenant. */
function berandaSignIn(Tenant $tenant): void
{
    $admin = UserFactory::new()->forTenant($tenant->id)->create([
        'email' => "admin@{$tenant->slug}.test",
    ]);

    app(TenantContext::class)->run(
        $tenant->id,
        fn () => $admin->assignTenantRole('admin-sekolah'),
    );

    actingAs($admin);
}

/** Sign in as a colleague holding only the teacher role. */
function berandaSignInAsGuru(Tenant $tenant): void
{
    $guru = UserFactory::new()->forTenant($tenant->id)->create([
        'email' => "guru@{$tenant->slug}.test",
    ]);

    app(TenantContext::class)->run(
        $tenant->id,
        fn () => $guru->assignTenantRole('guru'),
    );

    actingAs($guru);
}

function berandaVisit(Tenant $tenant)
{
    return get(school($tenant->slug, '/beranda'));
}

afterEach(fn () => Carbon::setTestNow());

it('shows the school record on the school\'s own clock', function () {
    Carbon::setTestNow('2026-09-30 02:00:00 UTC');
    $tenant = berandaTenant('sdn-garuda');
    berandaSignIn($tenant);

    berandaVisit($tenant)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Core/Beranda')
            ->where('school.name', $tenant->name)
            ->where('school.slug', 'sdn-garuda')
            ->where('school.timezone', 'Asia/Jakarta')
            // 02:00 UTC is 09:00 in Jakarta — the same calendar day.
            ->where('today.iso', '2026-09-30')
            ->where('today.label', 'Rabu, 30 September 2026')
            ->where('accounts.total', 1)
            ->where('accounts.active', 1)
            ->where('accounts.awaitingActivation', 0)
            ->where('accounts.deactivated', 0)
            ->where('accounts.withoutRole', 0)
            ->where('roles', ['Admin Sekolah', 'Guru', 'Staf/TU'])
            ->where('can.viewUsers', true)
            ->where('can.invite', true)
            ->etc());
});

it('dates the page by the school timezone, not the server\'s', function () {
    Carbon::setTestNow('2026-09-30 02:00:00 UTC');

    $jakarta = berandaTenant('sdn-jakarta', 'Asia/Jakarta');
    berandaSignIn($jakarta);
    berandaVisit($jakarta)->assertInertia(fn ($page) => $page
        ->where('today.iso', '2026-09-30'));

    // The same instant is still the previous evening in New York.
    $newYork = berandaTenant('sdn-newyork', 'America/New_York');
    berandaSignIn($newYork);
    berandaVisit($newYork)->assertInertia(fn ($page) => $page
        ->where('today.iso', '2026-09-29'));
});

it('reports the accounts of this school and no other', function () {
    $a = berandaTenant('sdn-a');
    $b = berandaTenant('sdn-b');
    berandaSignIn($b);
    berandaSignIn($a);
    UserFactory::new()->forTenant($a->id)->create(['email' => 'guru@sdn-a.test']);

    berandaVisit($a)->assertInertia(fn ($page) => $page
        ->where('accounts.total', 2)
        ->where('accounts.active', 2)
        ->where('accounts.withoutRole', 1));
});

it('counts an invited colleague as waiting and a refused one as kept', function () {
    $tenant = berandaTenant('sdn-campuran');
    berandaSignIn($tenant);
    UserFactory::new()->forTenant($tenant->id)->invited()->create([
        'email' => 'menunggu@sdn-campuran.test',
        'password' => null,
    ]);
    UserFactory::new()->forTenant($tenant->id)->deactivated()->create([
        'email' => 'lama@sdn-campuran.test',
    ]);

    berandaVisit($tenant)->assertInertia(fn ($page) => $page
        ->where('accounts.total', 3)
        ->where('accounts.active', 1)
        ->where('accounts.awaitingActivation', 1)
        ->where('accounts.deactivated', 1));
});

it('offers the invite action only with the permission to create users', function () {
    $tenant = berandaTenant('sdn-guru');
    berandaSignIn($tenant);

    berandaVisit($tenant)->assertInertia(fn ($page) => $page
        ->where('can.invite', true)
        ->where('can.viewUsers', true));

    berandaSignInAsGuru($tenant);

    berandaVisit($tenant)->assertInertia(fn ($page) => $page
        ->where('can.invite', false)
        ->where('can.viewUsers', false));
});

it('sends a guest to the tenant login', function () {
    berandaVisit(berandaTenant('sdn-tamu'))->assertRedirect(route('login'));
});

it('is the app root for a signed-in school member', function () {
    $tenant = berandaTenant('sdn-root');
    berandaSignIn($tenant);

    get(school('sdn-root', '/'))->assertRedirect(route('home'));
});

it('sends a tenant guest at the root to the login', function () {
    berandaTenant('sdn-root2');

    get(school('sdn-root2', '/'))->assertRedirect(route('login'));
});
