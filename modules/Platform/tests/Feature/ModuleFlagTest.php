<?php

use Illuminate\Support\Facades\Route;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\Exceptions\UnknownModuleException;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantModules as TenantModuleFlag;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\get;

beforeEach(function () {
    // Route exercising the middleware alias for a probe key.
    Route::get('/module-probe', fn (): string => 'ok')->middleware(['web', 'module:probe-module']);

    // Register the probe key (a business module would do this in its own provider).
    app(ModuleRegistry::class)->register('probe-module', ['label' => 'Probe']);
});

function flagTenant(string $slug): string
{
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    return $tenant->id;
}

it('rejects routes behind an inactive module with 403', function () {
    flagTenant('sekolah-a');

    get(school('sekolah-a', '/module-probe'))->assertForbidden();
});

it('lets routes through when the module is enabled for the tenant', function () {
    $a = flagTenant('sekolah-a');

    app(ModuleFlagManager::class)->enable($a, 'probe-module');

    get(school('sekolah-a', '/module-probe'))->assertOk();
});

it('always allows always-active modules like core', function () {
    flagTenant('sekolah-a');

    expect(app(TenantModules::class)->isEnabled('core'))->toBeTrue();
});

it('treats an expired flag as inactive', function () {
    $a = flagTenant('sekolah-a');

    app(ModuleFlagManager::class)->enable($a, 'probe-module', now()->subHour());

    get(school('sekolah-a', '/module-probe'))->assertForbidden();
});

it('reflects enable and disable changes immediately (cache invalidated)', function () {
    $a = flagTenant('sekolah-a');
    $modules = app(TenantModules::class);
    $manager = app(ModuleFlagManager::class);

    $manager->enable($a, 'probe-module');
    expect($modules->isEnabled('probe-module', $a))->toBeTrue();

    $manager->disable($a, 'probe-module');
    expect($modules->isEnabled('probe-module', $a))->toBeFalse();

    // Still false on a fresh lookup path (cached value was busted).
    expect(app(TenantModules::class)->isEnabled('probe-module', $a))->toBeFalse();
});

it('does not leak flags between tenants', function () {
    $a = flagTenant('sekolah-a');
    $b = flagTenant('sekolah-b');

    app(ModuleFlagManager::class)->enable($a, 'probe-module');

    expect(app(TenantModules::class)->isEnabled('probe-module', $a))->toBeTrue()
        ->and(app(TenantModules::class)->isEnabled('probe-module', $b))->toBeFalse();
});

it('rejects enabling an unregistered module key', function () {
    $a = flagTenant('sekolah-a');

    app(ModuleFlagManager::class)->enable($a, 'no-such-module');
})->throws(UnknownModuleException::class);

it('treats unknown keys as disabled in lookups (fail closed)', function () {
    $a = flagTenant('sekolah-a');

    expect(app(TenantModules::class)->isEnabled('no-such-module', $a))->toBeFalse();
});

it('throws when no tenant context and no tenant id given', function () {
    app(TenantModules::class)->isEnabled('probe-module');
})->throws(TenantNotSetException::class);

it('checks the current tenant when no tenant id is given', function () {
    $a = flagTenant('sekolah-a');
    $b = flagTenant('sekolah-b');

    app(ModuleFlagManager::class)->enable($a, 'probe-module');

    $context = app(TenantContext::class);

    $context->run($a, fn () => expect(app(TenantModules::class)->isEnabled('probe-module'))->toBeTrue());
    $context->run($b, fn () => expect(app(TenantModules::class)->isEnabled('probe-module'))->toBeFalse());
});

function probeFlagSource(string $tenantId): string
{
    return app(TenantContext::class)->run(
        $tenantId,
        fn (): string => TenantModuleFlag::query()->where('module', 'probe-module')->value('source'),
    );
}

it('records the source of a flag and never lets a plan write overwrite a manual one', function () {
    $a = flagTenant('sekolah-a');
    $manager = app(ModuleFlagManager::class);
    $modules = app(TenantModules::class);

    $manager->enable($a, 'probe-module');
    expect(probeFlagSource($a))->toBe('plan');

    $manager->disable($a, 'probe-module', TenantModuleFlag::SOURCE_MANUAL);
    expect(probeFlagSource($a))->toBe('manual');

    $manager->enable($a, 'probe-module');
    expect($modules->isEnabled('probe-module', $a))->toBeFalse();

    $manager->enable($a, 'probe-module', null, TenantModuleFlag::SOURCE_MANUAL);
    $manager->disable($a, 'probe-module');
    expect($modules->isEnabled('probe-module', $a))->toBeTrue();
});

it('writes manual flags from the tenant:modules command', function () {
    $a = flagTenant('sekolah-a');

    $this->artisan('tenant:modules', ['tenant' => 'sekolah-a', '--enable' => ['probe-module']])->assertSuccessful();

    expect(probeFlagSource($a))->toBe('manual');
});
