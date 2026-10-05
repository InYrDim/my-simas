<?php

use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Tenancy\SchoolCodeTenantResolver;
use Modules\Platform\App\Infrastructure\Tenancy\TenantMissingException;
use Modules\Platform\Database\Factories\InvoiceFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Provider console (real data): guard protection, page props, tenant
 * management, subscription actions, plans and invoices.
 */
function console(string $path): string
{
    return 'http://console.localhost'.$path;
}

function signInProvider(array $attributes = []): void
{
    actingAs(ProviderUserFactory::new()->create($attributes), 'provider');
}

dataset('console pages', [
    'dashboard' => ['/dashboard', 'Platform/ProviderHome'],
    'tenants' => ['/tenants', 'Platform/Tenants/Index'],
    'billing overview' => ['/billing', 'Platform/Billing/Index'],
    'subscriptions' => ['/billing/subscriptions', 'Platform/Billing/Subscriptions'],
    'plans' => ['/billing/plans', 'Platform/Billing/Plans'],
    'invoices' => ['/billing/invoices', 'Platform/Billing/Invoices'],
]);

it('keeps console pages and actions behind the provider guard', function () {
    $tenant = TenantFactory::new()->create();

    get(console('/dashboard'))->assertRedirect();
    get(console('/tenants'))->assertRedirect();
    get(console('/billing/plans'))->assertRedirect();
    post(console("/tenants/{$tenant->id}/suspend"))->assertRedirect();
    expect($tenant->refresh()->status)->toBe(TenantStatus::Active);
});

it('renders each console page for a provider user', function (string $path, string $component) {
    signInProvider();

    get(console($path))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with('console pages');

it('shares the signed-in provider with the console pages', function () {
    signInProvider(['name' => 'Ops Person']);

    get(console('/dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.provider.name', 'Ops Person'));
});

it('shows real dashboard figures', function () {
    signInProvider();
    $plan = PlanFactory::new()->create(['price_monthly' => 100_000]);
    SubscriptionFactory::new()->forPlan($plan->id)->active(30)->create();
    SubscriptionFactory::new()->forPlan($plan->id)->trialEndingIn(3)->create();
    TenantFactory::new()->suspended()->create();

    get(console('/dashboard'))->assertInertia(fn (Assert $page) => $page
        ->where('stats.activeTenants', 2)
        ->where('stats.suspendedTenants', 1)
        ->where('stats.trial', 1)
        ->where('stats.mrr', 100_000)
        ->has('attention', 1));
});

describe('tenants', function () {
    it('lists tenants and filters by mode and search', function () {
        signInProvider();
        $trial = SubscriptionFactory::new()->trialEndingIn(5)->create();
        SubscriptionFactory::new()->active()->create();
        TenantFactory::new()->create(['name' => 'Sekolah Tanpa Langganan']);

        get(console('/tenants'))
            ->assertInertia(fn (Assert $page) => $page->where('tenants.total', 3));

        get(console('/tenants?mode=trial'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenants.total', 1)
                ->where('tenants.data.0.id', $trial->tenant_id));

        get(console('/tenants?mode=none'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenants.total', 1)
                ->where('tenants.data.0.name', 'Sekolah Tanpa Langganan'));

        get(console('/tenants?q=Tanpa'))
            ->assertInertia(fn (Assert $page) => $page->where('tenants.total', 1));
    });

    it('shows a tenant with subscription, modules, roles and permissions', function () {
        signInProvider();
        $subscription = SubscriptionFactory::new()->create();

        get(console("/tenants/{$subscription->tenant_id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Platform/Tenants/Show')
                ->where('tenant.id', $subscription->tenant_id)
                ->where('subscription.state', 'trial')
                ->has('modules')
                ->has('roles')
                ->has('permissionCatalog')
                ->has('plans'));
    });

    it('returns 404 for an unknown tenant', function () {
        signInProvider();

        get(console('/tenants/does-not-exist'))->assertNotFound();
    });

    it('suspends and reactivates a tenant', function () {
        signInProvider();
        $tenant = TenantFactory::new()->create();

        post(console("/tenants/{$tenant->id}/suspend"))->assertRedirect();
        expect($tenant->refresh()->status)->toBe(TenantStatus::Suspended);

        post(console("/tenants/{$tenant->id}/activate"))->assertRedirect();
        expect($tenant->refresh()->status)->toBe(TenantStatus::Active);
    });

    it('updates the tenant profile and rejects a taken domain', function () {
        signInProvider();
        $tenant = TenantFactory::new()->create();
        TenantFactory::new()->create(['domain' => 'taken.sch.id']);

        put(console("/tenants/{$tenant->id}"), [
            'name' => 'SMA Baru',
            'timezone' => 'Asia/Makassar',
            'domain' => 'Sekolah.SCH.id',
        ])->assertSessionDoesntHaveErrors();

        expect($tenant->refresh())
            ->name->toBe('SMA Baru')
            ->timezone->toBe('Asia/Makassar')
            ->domain->toBe('sekolah.sch.id');

        put(console("/tenants/{$tenant->id}"), [
            'name' => 'SMA Baru',
            'timezone' => 'Asia/Makassar',
            'domain' => 'taken.sch.id',
        ])->assertSessionHasErrors('domain');
    });

    it('changes the school code and the old code stops resolving at once', function () {
        signInProvider();
        $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);
        $resolver = app(SchoolCodeTenantResolver::class);

        expect($resolver->resolve('sekolah-a')->id)->toBe($tenant->id);

        put(console("/tenants/{$tenant->id}/code"), ['code' => ' Sekolah-Baru '])
            ->assertSessionDoesntHaveErrors();

        expect($tenant->refresh()->slug)->toBe('sekolah-baru')
            ->and($resolver->resolve('sekolah-baru')->id)->toBe($tenant->id)
            ->and(fn () => $resolver->resolve('sekolah-a'))->toThrow(TenantMissingException::class);
    });

    it('refuses a school code that is malformed, reserved or taken', function (string $code) {
        signInProvider();
        $tenant = TenantFactory::new()->create(['slug' => 'sekolah-a']);
        TenantFactory::new()->create(['slug' => 'sudah-dipakai']);

        put(console("/tenants/{$tenant->id}/code"), ['code' => $code])->assertSessionHasErrors('code');

        expect($tenant->refresh()->slug)->toBe('sekolah-a');
    })->with(['spaces' => 'kode sekolah', 'underscore' => 'kode_sekolah', 'reserved' => 'admin', 'taken' => 'sudah-dipakai', 'too short' => 'ab']);

    it('sets module flags but keeps onboarding modules on', function () {
        signInProvider();
        $tenant = TenantFactory::new()->create();
        $modules = app(TenantModules::class);

        put(console("/tenants/{$tenant->id}/modules"), ['modules' => []])->assertRedirect();

        expect($modules->isEnabled('identity', $tenant->id))->toBeTrue();
    });
});

describe('tenant subscription actions', function () {
    it('starts a trial, activates a plan and activates it once the invoice is paid', function () {
        signInProvider();
        $plan = PlanFactory::new()->create(['key' => 'standard']);
        $tenant = TenantFactory::new()->create();

        post(console("/tenants/{$tenant->id}/subscription/trial"), ['plan' => 'standard', 'days' => 10])
            ->assertSessionHasNoErrors();

        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
        expect($subscription->status)->toBe(SubscriptionStatus::Trial);

        post(console("/tenants/{$tenant->id}/subscription/trial/extend"), ['days' => 5])->assertRedirect();

        post(console("/tenants/{$tenant->id}/subscription/activate"), ['plan' => 'standard', 'cycle' => 'yearly'])
            ->assertSessionHasNoErrors();

        $invoice = $subscription->invoices()->firstOrFail();
        expect($invoice->amount)->toBe($plan->price_yearly)
            ->and($invoice->status)->toBe(InvoiceStatus::Unpaid);

        post(console("/billing/invoices/{$invoice->id}/pay"))->assertSessionHasNoErrors();

        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
            ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
    });

    it('reports a billing error as a form error', function () {
        signInProvider();
        $tenant = TenantFactory::new()->create();

        post(console("/tenants/{$tenant->id}/subscription/trial"), ['plan' => 'missing'])
            ->assertSessionHasErrors('billing');
    });

    it('changes plan and cycle and cancels', function () {
        signInProvider();
        $other = PlanFactory::new()->create(['key' => 'pro']);
        $subscription = SubscriptionFactory::new()->active()->create();
        $tenantId = $subscription->tenant_id;

        put(console("/tenants/{$tenantId}/subscription/plan"), ['plan' => 'pro'])->assertSessionHasNoErrors();
        put(console("/tenants/{$tenantId}/subscription/cycle"), ['cycle' => 'yearly'])->assertSessionHasNoErrors();

        expect($subscription->refresh())
            ->plan_id->toBe($other->id)
            ->billing_cycle->value->toBe('yearly');

        post(console("/tenants/{$tenantId}/subscription/cancel"))->assertRedirect();

        expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Cancelled);
    });
});

describe('plans', function () {
    it('creates, edits, archives and restores a plan', function () {
        signInProvider();

        post(console('/billing/plans'), [
            'key' => 'gold',
            'name' => 'Gold',
            'price_monthly' => 500_000,
            'price_yearly' => 5_000_000,
            'max_users' => 200,
            'modules' => ['identity'],
            'is_active' => true,
        ])->assertRedirect(console('/billing/plans'));

        $plan = Plan::query()->where('key', 'gold')->firstOrFail();
        expect($plan->modules)->toBe(['core', 'identity'])
            ->and($plan->max_users)->toBe(200);

        put(console("/billing/plans/{$plan->id}"), [
            'name' => 'Gold Plus',
            'price_monthly' => 600_000,
            'price_yearly' => 6_000_000,
            'max_users' => null,
            'modules' => ['identity'],
        ])->assertSessionDoesntHaveErrors();

        expect($plan->refresh())->name->toBe('Gold Plus')->max_users->toBeNull();

        post(console("/billing/plans/{$plan->id}/archive"))->assertRedirect();
        expect($plan->refresh()->archived_at)->not->toBeNull()
            ->and(Plan::query()->selectable()->whereKey($plan->id)->exists())->toBeFalse();

        post(console("/billing/plans/{$plan->id}/restore"))->assertRedirect();
        expect($plan->refresh()->archived_at)->toBeNull();
    });

    it('validates a plan', function () {
        signInProvider();
        PlanFactory::new()->create(['key' => 'gold']);

        post(console('/billing/plans'), ['key' => 'Gold Plan', 'name' => '', 'price_monthly' => -1, 'price_yearly' => 'x', 'modules' => ['nope']])
            ->assertSessionHasErrors(['key', 'name', 'price_monthly', 'price_yearly', 'modules.0']);

        post(console('/billing/plans'), ['key' => 'gold', 'name' => 'Dup', 'price_monthly' => 1, 'price_yearly' => 1])
            ->assertSessionHasErrors('key');
    });

    it('never deletes a plan', function () {
        signInProvider();
        $plan = PlanFactory::new()->create();

        delete(console("/billing/plans/{$plan->id}"))->assertStatus(405);
        expect(Plan::query()->whereKey($plan->id)->exists())->toBeTrue();
    });
});

describe('invoices', function () {
    it('lists, filters, pays and voids invoices', function () {
        signInProvider();
        $subscription = SubscriptionFactory::new()->active()->create();
        $unpaid = InvoiceFactory::new()->create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
        ]);
        InvoiceFactory::new()->paid()->create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
        ]);

        get(console('/billing/invoices?status=unpaid'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoices.total', 1)
                ->where('invoices.data.0.number', $unpaid->number));

        post(console("/billing/invoices/{$unpaid->id}/void"))->assertRedirect();
        expect($unpaid->refresh()->status)->toBe(InvoiceStatus::Void);

        post(console("/billing/invoices/{$unpaid->id}/pay"))->assertSessionHasErrors('billing');
    });
});

it('has no leftover mock data class', function () {
    expect(class_exists('Modules\\Platform\\App\\Infrastructure\\Mock\\ProviderMockData'))->toBeFalse();
});
