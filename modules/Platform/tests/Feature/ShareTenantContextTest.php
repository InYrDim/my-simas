<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;

beforeEach(function () {
    // Route registered INSIDE the web group so the real middleware stack
    // (ResolveTenant → ShareTenantContext → HandleInertiaRequests) runs.
    // Inertia shares props only on Inertia responses, so the probe must
    // render an Inertia page.
    Route::middleware('web')->get('/tenant-share-probe', fn (): Response => Inertia::render('welcome'));
});

it('shares tenant and module props on a tenant host', function () {
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a', 'name' => 'SMA 1 Jakarta']);

    // identity is flag-controlled (unlike always-active core).
    app(ModuleFlagManager::class)->enable($tenant->id, 'identity');

    get('http://sekolah-a.localhost/tenant-share-probe')->assertOk();

    // The middleware wrote into Inertia's shared-prop registry; read it
    // back to assert exactly what the frontend would receive.
    $sharedTenant = Inertia::getShared('tenant');
    $modules = Inertia::getShared('modules');

    expect($sharedTenant)->not->toBeNull()
        ->and($sharedTenant['slug'])->toBe('sekolah-a')
        ->and($sharedTenant['timezone'])->toBe('Asia/Jakarta')
        ->and($modules)->toContain('core', 'identity');
});

it('shares null tenant and empty modules on central hosts', function () {
    get('http://localhost/tenant-share-probe')->assertOk();

    expect(Inertia::getShared('tenant'))->toBeNull()
        ->and(Inertia::getShared('modules'))->toBe([]);
});
