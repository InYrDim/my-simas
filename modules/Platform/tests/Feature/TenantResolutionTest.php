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

it('resolves a tenant from the school code in the request', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('/tenant-probe?school='.$tenant->id)
        ->assertOk()
        ->assertSee('tenant-id:sekolah-a', false);
});

it('remembers the school in the session for later requests', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('/tenant-probe?school='.$tenant->id)->assertSessionHas('tenant_id', $tenant->id);

    get('/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:sekolah-a', false);
});

it('runs without tenant context for an unknown or malformed school code', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('/tenant-probe?school=01ARZ3NDEKTSV4RRFFQ69G5FAV')
        ->assertOk()
        ->assertSee('tenant-id:central', false);

    get('/tenant-probe?school=Not%20A%20School!')
        ->assertOk()
        ->assertSee('tenant-id:central', false);
});

it('resolves a school by its slug as well', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('/tenant-probe?school=sekolah-a')
        ->assertOk()
        ->assertSee('tenant-id:sekolah-a', false);
});

it('forgets a remembered school when a new unknown code replaces it', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('/tenant-probe?school='.$tenant->id)->assertSee('tenant-id:sekolah-a', false);

    get('/tenant-probe?school=01ARZ3NDEKTSV4RRFFQ69G5FAV')
        ->assertSee('tenant-id:central', false)
        ->assertSessionMissing('tenant_id');
});

it('rejects a suspended tenant with 403', function () {
    $tenant = TenantFactory::new()->suspended()->create(['slug' => 'sekolah-b']);

    get('/tenant-probe?school='.$tenant->id)->assertForbidden();
});

it('runs without tenant context when no school is given', function () {
    get('/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:central', false);
});

it('never resolves a tenant from the host', function () {
    TenantFactory::new()->create(['slug' => 'sekolah-a', 'domain' => 'sekolah-a.sch.id']);

    get('http://sekolah-a.localhost/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:central', false);

    get('http://sekolah-a.sch.id/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:central', false);
});

it('never lets the console host carry a tenant', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('http://console.localhost/tenant-probe?school='.$tenant->id)
        ->assertOk()
        ->assertSee('tenant-id:central', false);
});

it('does not leak context between sequential requests', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);

    get('/tenant-probe?school='.$tenant->id)
        ->assertSee('tenant-id:sekolah-a', false);

    $this->flushSession();

    get('/tenant-probe')
        ->assertOk()
        ->assertSee('tenant-id:central', false);
});

it('keeps the session cookie host-only when SESSION_DOMAIN is null', function () {
    config()->set('session.domain', null);

    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);

    $response = get('/tenant-probe?school='.$tenant->id);

    collect($response->headers->getCookies())
        ->filter(fn (Cookie $cookie): bool => str_contains($cookie->getName(), 'session'))
        ->each(fn (Cookie $cookie) => expect($cookie->getDomain())->toBeNull());

    expect(config('session.domain'))->toBeNull();
});
