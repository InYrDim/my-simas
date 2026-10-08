<?php

use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Contracts\DTOs\UsageLine;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantUsage;
use Modules\Platform\App\Contracts\UsageMeters;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\App\Infrastructure\Usage\StorageMeter;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Usage against plan limits (fase 17, tahap 7). The Core and Identity
 * meters have their own tests; these use a probe meter.
 */
function usageProbeTenant(?array $limits = null): string
{
    $tenant = TenantFactory::new()->create();

    if ($limits !== null) {
        SubscriptionFactory::new()
            ->forTenant($tenant->id)
            ->forPlan(PlanFactory::new()->withLimits($limits)->create()->id)
            ->create();
    }

    return $tenant->id;
}

/**
 * Register a probe meter reading from a per-tenant figure map.
 *
 * @param  array<string, int>  $figures  tenant id => used
 */
function usageProbeMeter(ArrayObject $figures, string $module = 'platform'): void
{
    app(UsageMeters::class)->register(
        $module,
        'probe',
        'Probe',
        'unit',
        fn (): int => $figures[app(TenantContext::class)->id()] ?? 0,
        'students',
    );
}

function usageProbeLine(string $tenantId, string $key = 'probe'): ?UsageLine
{
    foreach (app(TenantUsage::class)->forTenant($tenantId) as $line) {
        if ($line->key === $key) {
            return $line;
        }
    }

    return null;
}

it('reports ok, near and over around the 90 percent mark', function (int $used, string $state) {
    $tenantId = usageProbeTenant(['students' => 100]);
    $figures = new ArrayObject([$tenantId => $used]);
    usageProbeMeter($figures);

    $line = usageProbeLine($tenantId);

    expect($line->used)->toBe($used)
        ->and($line->limit)->toBe(100)
        ->and($line->state)->toBe($state);
})->with([
    'well below' => [89, 'ok'],
    'at 90 percent' => [90, 'near'],
    'exactly at the limit' => [100, 'near'],
    'one above' => [101, 'over'],
]);

it('treats a missing limit, a missing key and a missing subscription as unlimited', function (?array $limits) {
    $tenantId = usageProbeTenant($limits);
    $figures = new ArrayObject([$tenantId => 5000]);
    usageProbeMeter($figures);

    $line = usageProbeLine($tenantId);

    expect($line->limit)->toBeNull()
        ->and($line->state)->toBe('ok');
})->with([
    'no subscription' => [null],
    'no limits at all' => [[]],
    'other key only' => [['staff_accounts' => 3]],
]);

it('hides the meters of a module that is not active for the school', function () {
    app(ModuleRegistry::class)->register('usage-probe-module', ['label' => 'Probe']);
    $on = usageProbeTenant();
    $off = usageProbeTenant();
    app(ModuleFlagManager::class)->enable($on, 'usage-probe-module');
    $figures = new ArrayObject([]);
    usageProbeMeter($figures, 'usage-probe-module');

    expect(usageProbeLine($on))->not->toBeNull()
        ->and(usageProbeLine($off))->toBeNull()
        ->and(usageProbeLine($off, 'storage'))->not->toBeNull();
});

it('computes every school on its own numbers', function () {
    $a = usageProbeTenant(['students' => 10]);
    $b = usageProbeTenant(['students' => 10]);
    $figures = new ArrayObject([$a => 4, $b => 11]);
    usageProbeMeter($figures);

    expect(usageProbeLine($a)->used)->toBe(4)
        ->and(usageProbeLine($a)->state)->toBe('ok')
        ->and(usageProbeLine($b)->used)->toBe(11)
        ->and(usageProbeLine($b)->state)->toBe('over');
});

it('answers isOverLimit and remaining for the current school without blocking', function () {
    $tenantId = usageProbeTenant(['students' => 10]);
    $figures = new ArrayObject([$tenantId => 12]);
    usageProbeMeter($figures);
    $usage = app(TenantUsage::class);

    app(TenantContext::class)->run($tenantId, function () use ($usage) {
        expect($usage->isOverLimit('probe'))->toBeTrue()
            ->and($usage->remaining('probe'))->toBe(0)
            ->and($usage->isOverLimit('nope'))->toBeFalse()
            ->and($usage->remaining('nope'))->toBeNull();
    });

    $figures[$tenantId] = 4;

    app(TenantContext::class)->run($tenantId, function () use ($usage) {
        expect($usage->isOverLimit('probe'))->toBeFalse()
            ->and($usage->remaining('probe'))->toBe(6);
    });
});

it('sums the bytes under the school directory of the private disk', function () {
    Storage::fake('local');
    $a = usageProbeTenant();
    $b = usageProbeTenant();
    Storage::disk('local')->put("tenants/{$a}/core/one.txt", str_repeat('x', 1000));
    Storage::disk('local')->put("tenants/{$a}/attendance/deep/two.txt", str_repeat('x', 500));
    Storage::disk('local')->put("tenants/{$b}/core/other.txt", str_repeat('x', 9999));
    Storage::disk('local')->put('central/core/central.txt', str_repeat('x', 9999));

    $bytes = app(TenantContext::class)->run($a, fn () => app(StorageMeter::class)->bytes());

    expect($bytes)->toBe(1500)
        ->and(usageProbeLine($a, 'storage')->used)->toBe(1)
        ->and(usageProbeLine($b, 'storage')->used)->toBe(1);
});

it('caches the storage figure for 15 minutes', function () {
    Storage::fake('local');
    $tenantId = usageProbeTenant();
    $meter = app(StorageMeter::class);
    $context = app(TenantContext::class);

    Storage::disk('local')->put("tenants/{$tenantId}/core/one.txt", str_repeat('x', 100));
    expect($context->run($tenantId, fn () => $meter->bytes()))->toBe(100);

    Storage::disk('local')->put("tenants/{$tenantId}/core/two.txt", str_repeat('x', 100));
    $this->travel(14)->minutes();
    expect($context->run($tenantId, fn () => $meter->bytes()))->toBe(100);

    $this->travel(2)->minutes();
    expect($context->run($tenantId, fn () => $meter->bytes()))->toBe(200);
});

it('caches an empty directory as zero rather than recomputing', function () {
    Storage::fake('local');
    $tenantId = usageProbeTenant();
    $meter = app(StorageMeter::class);
    $context = app(TenantContext::class);

    expect($context->run($tenantId, fn () => $meter->bytes()))->toBe(0);

    Storage::disk('local')->put("tenants/{$tenantId}/core/late.txt", 'abc');

    expect($context->run($tenantId, fn () => $meter->bytes()))->toBe(0);
});

it('shows usage on the tenant page and flags an over-limit school in the list', function () {
    $over = usageProbeTenant(['students' => 5]);
    $fine = usageProbeTenant(['students' => 500]);
    $figures = new ArrayObject([$over => 6, $fine => 6]);
    usageProbeMeter($figures);
    actingAs(ProviderUserFactory::new()->create(), 'provider');

    get('http://console.localhost/tenants/'.$over)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Platform/Tenants/Show')
        ->where('usage', fn ($usage) => collect($usage)->firstWhere('key', 'probe')['state'] === 'over'));

    get('http://console.localhost/tenants')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Platform/Tenants/Index')
        ->where('tenants.data', fn ($rows) => collect($rows)->firstWhere('id', $over)['overLimit'] === true
            && collect($rows)->firstWhere('id', $fine)['overLimit'] === false));
});
