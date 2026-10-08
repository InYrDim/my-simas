<?php

use Illuminate\Support\Carbon;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceKind;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\TenantModules as TenantModuleFlag;
use Modules\Platform\App\Infrastructure\Billing\InvoiceIssuer;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Tests\Support\FakePaymentGateway;

/**
 * Plan changes (fase 17, tahap 3): upgrade with proration, scheduled
 * downgrade, trial switch, provider override, and manual module flags
 * surviving plan syncs.
 */
beforeEach(function () {
    app(ModuleRegistry::class)->register('extra-mod', ['label' => 'Extra']);
});

function planChangeManager(): SubscriptionManager
{
    return app(SubscriptionManager::class);
}

function planChangePay(Invoice $invoice): void
{
    $gateway = FakePaymentGateway::paying();

    planChangeManager()->settle($gateway->initiate($invoice), $gateway->outcome($invoice));
}

function planChangeFlag(string $tenantId, string $module): bool
{
    return app(TenantModules::class)->isEnabled($module, $tenantId);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function planChangePlan(string $key, int $monthly, array $attributes = []): Plan
{
    return PlanFactory::new()->create([
        'key' => $key,
        'price_monthly' => $monthly,
        'price_yearly' => $monthly * 10,
        'modules' => $monthly >= 200_000 ? ['core', 'identity', 'extra-mod'] : ['core', 'identity'],
        ...$attributes,
    ]);
}

/**
 * An active monthly subscription on a 30-day period, 10 days in (20 days
 * left), with the plan's modules synced.
 */
function planChangeActive(Plan $plan, ?BillingCycle $cycle = null): Subscription
{
    $tenant = TenantFactory::new()->create();
    $manager = planChangeManager();

    $manager->startTrial($tenant->id, $plan->key);

    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->firstOrFail();
    $subscription->forceFill([
        'status' => 'active',
        'billing_cycle' => $cycle ?? BillingCycle::Monthly,
        'trial_ends_at' => null,
        'current_period_start' => Carbon::today()->subDays(10),
        'current_period_end' => Carbon::today()->addDays(20),
    ])->save();

    return $subscription;
}

it('keeps a module the provider switched on by hand through renewal, upgrade and downgrade', function () {
    $cheap = planChangePlan('cheap', 100_000);
    planChangePlan('mid', 150_000);
    planChangePlan('dear', 250_000);
    $subscription = planChangeActive($cheap);

    app(ModuleFlagManager::class)->enable($subscription->tenant_id, 'extra-mod', null, TenantModuleFlag::SOURCE_MANUAL);

    planChangePay(planChangeManager()->renew($subscription));
    expect(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeTrue();

    planChangePay(planChangeManager()->requestPlanChange($subscription->refresh(), 'dear')->upgradeInvoice);
    expect($subscription->refresh()->plan->key)->toBe('dear')
        ->and(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeTrue();

    planChangeManager()->requestPlanChange($subscription, 'cheap');
    planChangePay(planChangeManager()->renew($subscription->refresh()));

    expect($subscription->refresh()->plan->key)->toBe('cheap')
        ->and(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeTrue();
});

it('does not switch a module back on that the provider switched off by hand', function () {
    planChangePlan('cheap', 100_000);
    planChangePlan('dear', 250_000);
    $subscription = planChangeActive(Plan::query()->where('key', 'cheap')->firstOrFail());

    planChangeManager()->changePlan($subscription, 'dear');
    expect(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeTrue();

    app(ModuleFlagManager::class)->disable($subscription->tenant_id, 'extra-mod', TenantModuleFlag::SOURCE_MANUAL);
    planChangeManager()->changePlan($subscription->refresh(), 'cheap');
    planChangeManager()->changePlan($subscription->refresh(), 'dear');
    planChangePay(planChangeManager()->renew($subscription->refresh()));

    expect(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeFalse();
});

it('still lets plan flags follow the plan', function () {
    planChangePlan('cheap', 100_000);
    planChangePlan('dear', 250_000);
    $subscription = planChangeActive(Plan::query()->where('key', 'cheap')->firstOrFail());

    expect(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeFalse();

    planChangeManager()->changePlan($subscription, 'dear');
    expect(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeTrue();

    planChangeManager()->changePlan($subscription->refresh(), 'cheap');
    expect(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeFalse();
});

it('rounds the prorated difference up to whole rupiah', function () {
    $issuer = app(InvoiceIssuer::class);

    expect($issuer->proratedAmount(150_000, 20, 30))->toBe(100_000)
        ->and($issuer->proratedAmount(1, 20, 30))->toBe(1)
        ->and($issuer->proratedAmount(1000, 20, 30))->toBe(667)
        ->and($issuer->proratedAmount(100_000, 1, 30))->toBe(3334);
});

it('issues an upgrade invoice for the prorated difference and changes nothing until it is paid', function () {
    $cheap = planChangePlan('cheap', 100_000);
    planChangePlan('dear', 250_000);
    $subscription = planChangeActive($cheap);
    $start = $subscription->current_period_start->toDateString();
    $end = $subscription->current_period_end->toDateString();

    $result = planChangeManager()->requestPlanChange($subscription, 'dear');
    $invoice = $result->upgradeInvoice;

    // (250_000 - 100_000) x 20 days left / 30 days in the period
    expect($result->isUpgrade())->toBeTrue()
        ->and($invoice->kind)->toBe(InvoiceKind::Upgrade)
        ->and($invoice->amount)->toBe(100_000)
        ->and($invoice->period_start->toDateString())->toBe(Carbon::today()->toDateString())
        ->and($invoice->period_end->toDateString())->toBe($end)
        ->and($subscription->refresh()->plan_id)->toBe($cheap->id)
        ->and(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeFalse();

    planChangePay($invoice);

    expect($subscription->refresh()->plan->key)->toBe('dear')
        ->and($subscription->current_period_start->toDateString())->toBe($start)
        ->and($subscription->current_period_end->toDateString())->toBe($end)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->period_end->toDateString())->toBe($end)
        ->and(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeTrue();
});

it('voids a stale unpaid invoice when upgrading and refuses to pay it', function () {
    $cheap = planChangePlan('cheap', 100_000);
    planChangePlan('dear', 250_000);
    $subscription = planChangeActive($cheap);

    $renewal = planChangeManager()->renew($subscription);
    $gateway = FakePaymentGateway::paying();
    $payment = $gateway->initiate($renewal);

    $upgrade = planChangeManager()->requestPlanChange($subscription->refresh(), 'dear')->upgradeInvoice;

    expect($renewal->refresh()->status)->toBe(InvoiceStatus::Void);

    expect(fn () => planChangeManager()->settle($payment, $gateway->outcome($renewal)))
        ->toThrow(BillingException::class);

    expect($subscription->refresh()->plan_id)->toBe($cheap->id)
        ->and($upgrade->refresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('cannot revert a plan change by paying an invoice issued before it', function () {
    $cheap = planChangePlan('cheap', 100_000);
    planChangePlan('dear', 250_000);
    $subscription = planChangeActive($cheap);

    $renewal = planChangeManager()->renew($subscription);
    planChangeManager()->changePlan($subscription->refresh(), 'dear');

    expect($renewal->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and(fn () => planChangePay($renewal))->toThrow(BillingException::class)
        ->and($subscription->refresh()->plan->key)->toBe('dear');
});

it('schedules a downgrade, renews on the scheduled plan and then clears it', function () {
    $dear = planChangePlan('dear', 250_000);
    $cheap = planChangePlan('cheap', 100_000);
    $subscription = planChangeActive($dear);
    $end = $subscription->current_period_end->toDateString();

    $result = planChangeManager()->requestPlanChange($subscription, 'cheap');

    expect($result->isScheduled())->toBeTrue()
        ->and($result->scheduledPlan->id)->toBe($cheap->id)
        ->and($subscription->refresh()->scheduled_plan_id)->toBe($cheap->id)
        ->and($subscription->plan_id)->toBe($dear->id)
        ->and(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeTrue()
        ->and(Invoice::query()->count())->toBe(0);

    $renewal = planChangeManager()->renew($subscription);

    expect($renewal->plan_id)->toBe($cheap->id)
        ->and($renewal->amount)->toBe(100_000)
        ->and($renewal->period_start->toDateString())->toBe($end);

    planChangePay($renewal);

    expect($subscription->refresh()->plan_id)->toBe($cheap->id)
        ->and($subscription->scheduled_plan_id)->toBeNull()
        ->and(planChangeFlag($subscription->tenant_id, 'extra-mod'))->toBeFalse();
});

it('cancels a scheduled downgrade and voids a renewal issued for it', function () {
    $dear = planChangePlan('dear', 250_000);
    planChangePlan('cheap', 100_000);
    $subscription = planChangeActive($dear);

    planChangeManager()->requestPlanChange($subscription, 'cheap');
    $renewal = planChangeManager()->renew($subscription->refresh());

    planChangeManager()->cancelScheduledChange($subscription->refresh());

    expect($subscription->refresh()->scheduled_plan_id)->toBeNull()
        ->and($renewal->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and(planChangeManager()->renew($subscription)->plan_id)->toBe($dear->id);
});

it('treats an equal-price switch as a downgrade', function () {
    $first = planChangePlan('first', 100_000);
    $second = planChangePlan('second', 100_000);
    $subscription = planChangeActive($first);

    $result = planChangeManager()->requestPlanChange($subscription, 'second');

    expect($result->isScheduled())->toBeTrue()
        ->and($subscription->refresh()->scheduled_plan_id)->toBe($second->id)
        ->and($subscription->plan_id)->toBe($first->id);
});

it('compares prices at the subscription cycle', function () {
    $monthlyCheaper = planChangePlan('a', 100_000, ['price_yearly' => 2_000_000]);
    planChangePlan('b', 150_000, ['price_yearly' => 1_500_000]);

    $monthly = planChangeActive($monthlyCheaper);
    $yearly = planChangeActive($monthlyCheaper, BillingCycle::Yearly);
    $yearly->forceFill([
        'current_period_start' => Carbon::today()->subDays(65),
        'current_period_end' => Carbon::today()->addDays(300),
    ])->save();

    expect(planChangeManager()->requestPlanChange($monthly, 'b')->isUpgrade())->toBeTrue()
        ->and(planChangeManager()->requestPlanChange($yearly, 'b')->isScheduled())->toBeTrue();
});

it('switches plan and modules at once during a trial, with no invoice', function () {
    planChangePlan('cheap', 100_000);
    $dear = planChangePlan('dear', 250_000);
    $tenant = TenantFactory::new()->create();
    $subscription = planChangeManager()->startTrial($tenant->id, 'cheap');

    $activation = planChangeManager()->activate($subscription, 'cheap', BillingCycle::Monthly);

    $result = planChangeManager()->requestPlanChange($subscription->refresh(), 'dear');

    expect($result->isImmediate())->toBeTrue()
        ->and($subscription->refresh()->plan_id)->toBe($dear->id)
        ->and(planChangeFlag($tenant->id, 'extra-mod'))->toBeTrue()
        ->and($activation->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and(Invoice::query()->where('kind', InvoiceKind::Upgrade)->count())->toBe(0);
});

it('lets a school request only public plans, but the provider override can assign a private one', function () {
    $cheap = planChangePlan('cheap', 100_000);
    $private = PlanFactory::new()->private()->create(['key' => 'custom', 'price_monthly' => 300_000, 'price_yearly' => 3_000_000]);
    $subscription = planChangeActive($cheap);

    expect(fn () => planChangeManager()->requestPlanChange($subscription, 'custom'))->toThrow(BillingException::class);

    planChangeManager()->changePlan($subscription, 'custom');

    expect($subscription->refresh()->plan_id)->toBe($private->id)
        ->and(Invoice::query()->count())->toBe(0);
});

it('refuses a plan change request for the plan already held or outside a paid period', function () {
    $cheap = planChangePlan('cheap', 100_000);
    planChangePlan('dear', 250_000);
    $subscription = planChangeActive($cheap);

    expect(fn () => planChangeManager()->requestPlanChange($subscription, 'cheap'))->toThrow(BillingException::class);

    $subscription->forceFill(['current_period_end' => Carbon::today()->subDay()])->save();

    expect(fn () => planChangeManager()->requestPlanChange($subscription, 'dear'))->toThrow(BillingException::class);
});

it('clears a scheduled downgrade and voids open invoices on a provider override', function () {
    $dear = planChangePlan('dear', 250_000);
    planChangePlan('cheap', 100_000);
    planChangePlan('mid', 150_000);
    $subscription = planChangeActive($dear);

    planChangeManager()->requestPlanChange($subscription, 'cheap');
    $renewal = planChangeManager()->renew($subscription->refresh());

    planChangeManager()->changePlan($subscription->refresh(), 'mid');

    expect($subscription->refresh()->scheduled_plan_id)->toBeNull()
        ->and($subscription->plan->key)->toBe('mid')
        ->and($renewal->refresh()->status)->toBe(InvoiceStatus::Void);
});

it('voids an open invoice when the cycle changes so the next one uses the new cycle', function () {
    $cheap = planChangePlan('cheap', 100_000);
    $subscription = planChangeActive($cheap);

    $monthlyRenewal = planChangeManager()->renew($subscription);
    planChangeManager()->changeCycle($subscription->refresh(), BillingCycle::Yearly);

    $yearlyRenewal = planChangeManager()->renew($subscription->refresh());

    expect($monthlyRenewal->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and($yearlyRenewal->billing_cycle)->toBe(BillingCycle::Yearly)
        ->and($yearlyRenewal->amount)->toBe(1_000_000);
});
