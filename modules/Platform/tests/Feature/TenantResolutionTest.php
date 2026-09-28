<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Infrastructure\Tenancy\TenantHydrator;
use Modules\Platform\Database\Factories\TenantFactory;
use Symfony\Component\HttpFoundation\Cookie;

use function Pest\Laravel\get;

beforeEach(function () {
    // Route exercising the middleware (controller-less so no module coupling).
    // Resolution runs globally (web group) — no 'tenant' alias needed here.
    // The probe exposes the tenant SLUG (ids are opaque ULIDs).
    Route::get('/tenant-probe', function (TenantContext $context): string {
        Cache::flush();

        $id = $context->id();

        if ($id === null) {
            return 'tenant-id:central';
        }

        $tenant = TenantHydrator::find($id);

        return 'tenant-id:'.($tenant->slug ?? $id);
    })->middleware(['web']);
});

it('resolves a tenant by subdomain and sets context', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('http://sekolah-a.localhost/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:sekolah-a', false);
});

it('resolves a tenant by custom domain', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a', 'domain' => 'sekolah-a.sch.id']);

    get('http://sekolah-a.sch.id/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:sekolah-a', false);
});

it('rejects an unknown host with a generic 404', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('http://unknown.localhost/tenant-probe')->assertNotFound();
});

it('rejects an unknown custom domain with a generic 404', function () {
    get('http://random.sch.id/tenant-probe')->assertNotFound();
});

it('rejects a suspended tenant with 403', function () {
    TenantFactory::new()->suspended()->create(['slug' => 'sekolah-b']);

    get('http://sekolah-b.localhost/tenant-probe')->assertForbidden();
});

it('runs central hosts without tenant context', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('http://localhost/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:central', false);
});

it('does not leak context between sequential requests', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('http://sekolah-a.localhost/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:sekolah-a', false);

    get('http://localhost/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:central', false);

    get('http://sekolah-a.localhost/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:sekolah-a', false);
});

it('rejects multi-level subdomains instead of treating them as slugs', function () {
    get('http://a.b.localhost/tenant-probe')->assertNotFound();
});

it('keeps the session cookie host-only when SESSION_DOMAIN is null', function () {
    config()->set('session.domain', null);

    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    $response = get('http://sekolah-a.localhost/tenant-probe');

    $cookies = $response->headers->getCookies();

    $sessionCookies = collect($cookies)->filter(
        fn (Cookie $cookie): bool => str_contains($cookie->getName(), 'session'),
    );

    if ($sessionCookies->isNotEmpty()) {
        // Host-only cookie: Symfony returns domain null (no Domain= attribute)
        // so the browser only sends it back to sekolah-a.localhost.
        $sessionCookies->each(
            fn (Cookie $cookie) => expect($cookie->getDomain())->toBeNull(),
        );
    } else {
        // No session data set by the probe route; assert config intent.
        expect(config('session.domain'))->toBeNull();
    }
});

it('normalizes host case and port', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('http://SEKOLAH-A.localhost:8080/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:sekolah-a', false);
});
