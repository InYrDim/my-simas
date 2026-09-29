<?php

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

beforeEach(function () {
    // Register a probe module key the way a real module would.
    app(ModuleRegistry::class)->register('probe-module', ['label' => 'Probe']);
});

function createTenantViaFactory(string $slug): string
{
    /** @var Tenant $tenant */
    $tenant = TenantFactory::new()->create(['slug' => $slug]);

    return $tenant->id;
}

function artisanOutput(string $command): string
{
    Artisan::call($command);

    return Artisan::output();
}

class ProbeTenantContextCommand extends Command
{
    protected $signature = 'probe:tenant-context';

    public function handle(TenantContext $context): int
    {
        $this->line('ctx='.($context->id() ?? 'null'));

        return self::SUCCESS;
    }
}

it('creates a tenant with valid slug, timezone, and modules', function () {
    $output = artisanOutput('tenant:create "SMA Uji" uji-coba --timezone=Asia/Makassar --modules=probe-module');

    expect($output)->toContain('created');

    $tenant = Tenant::query()->where('slug', 'uji-coba')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->timezone)->toBe('Asia/Makassar')
        ->and($tenant->status->value)->toBe('active')
        ->and(app(TenantModules::class)->isEnabled('probe-module', $tenant->id))->toBeTrue();
});

it('rejects reserved slugs', function () {
    $output = artisanOutput('tenant:create "Admin" admin');

    expect($output)->toContain('reserved')
        ->and(Tenant::query()->where('slug', 'admin')->exists())->toBeFalse();
});

it('rejects duplicate slugs', function () {
    createTenantViaFactory('sudah-ada');

    $output = artisanOutput('tenant:create "Dobel" sudah-ada');

    expect($output)->toContain('already taken');
});

it('rejects invalid timezones', function () {
    $output = artisanOutput('tenant:create "Uji" uji-zona --timezone=Mars/Olympus');

    expect($output)->toContain('not a valid IANA timezone')
        ->and(Tenant::query()->where('slug', 'uji-zona')->exists())->toBeFalse();
});

it('rejects unregistered module keys', function () {
    $output = artisanOutput('tenant:create "Uji" uji-modul --modules=ghost');

    expect($output)->toContain('not registered')
        ->and(Tenant::query()->where('slug', 'uji-modul')->exists())->toBeFalse();
});

it('suspends and reactivates tenants', function () {
    createTenantViaFactory('status-uji');

    expect(artisanOutput('tenant:suspend status-uji'))->toContain('suspended');
    expect(Tenant::query()->where('slug', 'status-uji')->first()->status->value)->toBe('suspended');

    expect(artisanOutput('tenant:activate status-uji'))->toContain('activated');
    expect(Tenant::query()->where('slug', 'status-uji')->first()->status->value)->toBe('active');
});

it('enables and disables modules via tenant:modules', function () {
    $tenantId = createTenantViaFactory('flag-uji');

    expect(artisanOutput('tenant:modules flag-uji --enable=probe-module'))->toContain('Enabled');
    expect(app(TenantModules::class)->isEnabled('probe-module', $tenantId))->toBeTrue();

    expect(artisanOutput('tenant:modules flag-uji --disable=probe-module'))->toContain('Disabled');
    expect(app(TenantModules::class)->isEnabled('probe-module', $tenantId))->toBeFalse();
});

it('runs a command inside the tenant context', function () {
    $tenantId = createTenantViaFactory('run-uji');

    // A command registered through the kernel (same path a real
    // module's commands take) that reports the ambient tenant id.
    app()->singleton(ProbeTenantContextCommand::class);
    app(Kernel::class)->registerCommand(app(ProbeTenantContextCommand::class));

    $output = artisanOutput('tenant:run run-uji probe:tenant-context');

    expect($output)->toContain('ctx='.$tenantId)
        ->and($output)->toContain('finished with exit code 0');
});

it('syncs permissions idempotently without touching assignments', function () {
    app(PermissionRegistry::class)->register('probe', ['probe.one', 'probe.two']);

    expect(artisanOutput('permissions:sync'))->toContain('Created 2 permission');

    // Second run: nothing new.
    expect(artisanOutput('permissions:sync'))->toContain('Nothing created');

    // An assignment must survive a resync.
    $permissionId = DB::table('permissions')->where('name', 'probe.one')->value('id');
    DB::table('model_has_permissions')->insert([
        'permission_id' => $permissionId,
        'model_type' => 'test-model',
        'model_id' => 1,
        'tenant_id' => createTenantViaFactory('sync-uji'),
    ]);

    expect(artisanOutput('permissions:sync'))->toContain('Nothing created');

    $assignment = DB::table('model_has_permissions')
        ->where('permission_id', $permissionId)
        ->where('model_type', 'test-model')
        ->count();

    expect($assignment)->toBe(1);
});

it('creates a provider user with a hashed password', function () {
    $output = artisanOutput('provider:create-user "Ops User" ops@provider.test --password=rahasia123');

    expect($output)->toContain('created');

    $user = ProviderUser::query()->where('email', 'ops@provider.test')->first();

    expect($user)->not->toBeNull()
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('rejects duplicate provider emails', function () {
    ProviderUser::query()->create([
        'name' => 'Ada',
        'email' => 'dupe@provider.test',
        'password' => 'rahasia123',
    ]);

    $output = artisanOutput('provider:create-user "Dobel" dupe@provider.test --password=rahasia123');

    expect($output)->toContain('already been taken');
});
