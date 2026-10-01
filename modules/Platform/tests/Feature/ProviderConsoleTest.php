<?php

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Infrastructure\Mock\ProviderMockData;
use Modules\Platform\Database\Factories\ProviderUserFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Provider console UI-first pages (mock data). These prove routing,
 * guard protection and prop shape only; real data arrives later.
 */
dataset('console pages', [
    'dashboard' => ['/dashboard', 'Platform/ProviderHome'],
    'tenants' => ['/tenants', 'Platform/Tenants/Index'],
    'users' => ['/users', 'Platform/Users/Index'],
    'billing overview' => ['/billing', 'Platform/Billing/Index'],
    'subscriptions' => ['/billing/subscriptions', 'Platform/Billing/Subscriptions'],
    'plans' => ['/billing/plans', 'Platform/Billing/Plans'],
    'invoices' => ['/billing/invoices', 'Platform/Billing/Invoices'],
]);

it('keeps console pages behind the provider guard', function (string $path) {
    get('http://console.localhost'.$path)->assertRedirect();
})->with(fn () => array_map(fn (array $row): array => [$row[0]], [
    ['/dashboard'], ['/tenants'], ['/users'], ['/billing'],
]));

it('renders each console page for a provider user', function (string $path, string $component) {
    actingAs(ProviderUserFactory::new()->create(), 'provider');

    get('http://console.localhost'.$path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with('console pages');

it('shows a tenant detail with modules, roles and permissions', function () {
    actingAs(ProviderUserFactory::new()->create(), 'provider');

    $tenant = ProviderMockData::tenants()[0];

    get('http://console.localhost/tenants/'.$tenant['id'])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Tenants/Show')
            ->where('tenant.id', $tenant['id'])
            ->has('modules')
            ->has('roles')
            ->has('permissionCatalog'));
});

it('returns 404 for an unknown tenant', function () {
    actingAs(ProviderUserFactory::new()->create(), 'provider');

    get('http://console.localhost/tenants/does-not-exist')->assertNotFound();
});

it('shares the signed-in provider with the console pages', function () {
    actingAs(ProviderUserFactory::new()->create(['name' => 'Ops Person']), 'provider');

    get('http://console.localhost/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.provider.name', 'Ops Person'));
});
