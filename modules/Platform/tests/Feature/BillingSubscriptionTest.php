<?php

use Illuminate\Support\Carbon;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceKind;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Infrastructure\Billing\BillingSummary;
use Modules\Platform\App\Infrastructure\Billing\InvoiceIssuer;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Modules\Platform\Database\Factories\ApplicantFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Tests\Support\FakePaymentGateway;

/**
 * Provider-side subscription billing: trial, activation, invoices, the
 * always-true payment stub, derived states and the revenue summary.
 */
function subscriptions(): SubscriptionManager
{
    return app(SubscriptionManager::class);
}

/**
 * Pays an invoice the way a gateway would: initiate, then settle with
 * the outcome the fake reports.
 */
function payInvoice(Invoice $invoice, ?FakePaymentGateway $gateway = null): Payment
{
    $gateway ??= FakePaymentGateway::paying();

    return subscriptions()->settle($gateway->initiate($invoice), $gateway->outcome($invoice));
}

it('starts a 14-day trial on the chosen plan and enables its modules', function () {
    $plan = PlanFactory::new()->create(['key' => 'starter', 'modules' => ['core', 'identity']]);
    $tenant = TenantFactory::new()->create();

    $subscription = subscriptions()->startTrial($tenant->id, 'starter');

    expect($subscription->status)->toBe(SubscriptionStatus::Trial)
        ->and($subscription->plan_id)->toBe($plan->id)
        ->and($subscription->trial_ends_at->toDateString())->toBe(Carbon::today()->addDays(14)->toDateString())
        ->and($subscription->isTrial())->toBeTrue()
        ->and($subscription->displayState())->toBe('trial')
        ->and(app(TenantModules::class)->isEnabled('identity', $tenant->id))->toBeTrue();
});

it('refuses a second subscription or an unavailable plan', function () {
    PlanFactory::new()->create(['key' => 'starter']);
    PlanFactory::new()->archived()->create(['key' => 'old']);
    $tenant = TenantFactory::new()->create();

    subscriptions()->startTrial($tenant->id, 'starter');

    expect(fn () => subscriptions()->startTrial($tenant->id, 'starter'))->toThrow(BillingException::class)
        ->and(fn () => subscriptions()->startTrial(TenantFactory::new()->create()->id, 'old'))->toThrow(BillingException::class);
});

it('starts a trial automatically when an application is approved', function () {
    PlanFactory::new()->create(['key' => 'starter']);
    $applications = app(TenantApplications::class);

    $applications->submit(ApplicantFactory::new()->create()->id, [
        'school_name' => 'SMA Negeri Uji Coba',
        'desired_slug' => 'sman-uji',
    ]);

    $applications->approve($applications->pending()[0]->id, ProviderUserFactory::new()->create()->id);

    $subscription = Subscription::query()->firstOrFail();

    expect($subscription->status)->toBe(SubscriptionStatus::Trial)
        ->and($subscription->tenant->slug)->toBe('sman-uji');
});

it('still approves an application when no trial plan is seeded', function () {
    $applications = app(TenantApplications::class);

    $applications->submit(ApplicantFactory::new()->create()->id, [
        'school_name' => 'SMA Negeri Uji Coba',
        'desired_slug' => 'sman-uji',
    ]);

    $applications->approve($applications->pending()[0]->id, ProviderUserFactory::new()->create()->id);

    expect(Subscription::query()->count())->toBe(0);
});

it('extends only a trial', function () {
    $subscription = SubscriptionFactory::new()->trialEndingIn(3)->create();

    $extended = subscriptions()->extendTrial($subscription, 7);

    expect($extended->trial_ends_at->toDateString())->toBe(Carbon::today()->addDays(10)->toDateString());

    $active = SubscriptionFactory::new()->active()->create();

    expect(fn () => subscriptions()->extendTrial($active, 7))->toThrow(BillingException::class);
});

it('activates a plan by issuing an unpaid invoice and settles it once paid', function () {
    $plan = PlanFactory::new()->create(['key' => 'standard', 'price_monthly' => 350_000, 'price_yearly' => 3_500_000]);
    $subscription = SubscriptionFactory::new()->trialEndingIn(5)->create();

    $invoice = subscriptions()->activate($subscription, 'standard', BillingCycle::Yearly);

    expect($invoice->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->amount)->toBe(3_500_000)
        ->and($invoice->plan_name)->toBe($plan->name)
        ->and($invoice->number)->toMatch('/^INV-\d{4}-0001$/')
        ->and($invoice->kind)->toBe(InvoiceKind::Activation)
        ->and($invoice->due_at->toDateString())->toBe(Carbon::today()->addDays(7)->toDateString())
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Trial);

    expect(payInvoice($invoice)->status)->toBe(PaymentStatus::Paid);

    $subscription->refresh();
    $invoice->refresh();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->paid_at)->not->toBeNull()
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->billing_cycle)->toBe(BillingCycle::Yearly)
        ->and($subscription->current_period_start->toDateString())->toBe(Carbon::today()->toDateString())
        ->and($subscription->current_period_end->toDateString())->toBe(Carbon::today()->addMonthsNoOverflow(12)->toDateString());
});

it('continues the period from its end when renewing an active subscription', function () {
    PlanFactory::new()->create();
    $subscription = SubscriptionFactory::new()->active(10)->create();
    $end = $subscription->current_period_end->copy();

    $invoice = subscriptions()->renew($subscription);

    expect($invoice->period_start->toDateString())->toBe($end->toDateString());

    payInvoice($invoice);

    expect($subscription->refresh()->current_period_end->toDateString())
        ->toBe($end->copy()->addMonthNoOverflow()->toDateString());
});

it('restarts from today when a lapsed subscription is paid', function () {
    $subscription = SubscriptionFactory::new()->active(-5)->create();

    $invoice = subscriptions()->renew($subscription);
    payInvoice($invoice);

    expect($subscription->refresh()->current_period_start->toDateString())->toBe(Carbon::today()->toDateString());
});

it('reactivates a cancelled subscription once its invoice is paid', function () {
    PlanFactory::new()->create(['key' => 'starter']);
    $subscription = SubscriptionFactory::new()->active()->cancelled()->create();

    $invoice = subscriptions()->activate($subscription, 'starter', BillingCycle::Monthly);

    expect($subscription->refresh()->displayState())->toBe('cancelled');

    payInvoice($invoice);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->cancelled_at)->toBeNull();
});

it('leaves everything untouched when the gateway declines', function () {
    $subscription = SubscriptionFactory::new()->trialEndingIn(5)->create();
    $invoice = subscriptions()->activate($subscription, $subscription->plan->key, BillingCycle::Monthly);

    expect(payInvoice($invoice, FakePaymentGateway::failing())->status)->toBe(PaymentStatus::Failed)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Trial);
});

it('ignores a second payment on a paid invoice and refuses to void it', function () {
    $subscription = SubscriptionFactory::new()->active()->create();
    $paid = subscriptions()->renew($subscription);
    payInvoice($paid);

    expect(payInvoice($paid)->status)->toBe(PaymentStatus::Pending)
        ->and($paid->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and(fn () => app(InvoiceIssuer::class)->void($paid))->toThrow(BillingException::class);
});

it('voids an unpaid invoice and numbers invoices sequentially', function () {
    $subscription = SubscriptionFactory::new()->active()->create();

    $first = subscriptions()->renew($subscription);
    subscriptions()->changeCycle($subscription, BillingCycle::Yearly);
    $second = subscriptions()->renew($subscription);

    expect($first->number)->toEndWith('-0001')
        ->and($second->number)->toEndWith('-0002')
        ->and($first->refresh()->status)->toBe(InvoiceStatus::Void);

    app(InvoiceIssuer::class)->void($second);

    expect($second->refresh()->status)->toBe(InvoiceStatus::Void);
});

it('syncs plan modules on plan change but never disables onboarding modules', function () {
    app(ModuleRegistry::class)->register('absensi-test');

    PlanFactory::new()->create(['key' => 'with-extra', 'modules' => ['core', 'identity', 'absensi-test']]);
    PlanFactory::new()->create(['key' => 'basic', 'modules' => ['core']]);

    $tenant = TenantFactory::new()->create();
    $subscription = subscriptions()->startTrial($tenant->id, 'with-extra');
    $modules = app(TenantModules::class);

    expect($modules->isEnabled('absensi-test', $tenant->id))->toBeTrue();

    subscriptions()->changePlan($subscription, 'basic');

    expect($modules->isEnabled('absensi-test', $tenant->id))->toBeFalse()
        ->and($modules->isEnabled('identity', $tenant->id))->toBeTrue();
});

it('derives the display state from dates', function () {
    expect(SubscriptionFactory::new()->trialEndingIn(10)->make()->displayState())->toBe('trial')
        ->and(SubscriptionFactory::new()->trialEndingIn(-1)->make()->displayState())->toBe('trial_expired')
        ->and(SubscriptionFactory::new()->active(30)->make()->displayState())->toBe('active')
        ->and(SubscriptionFactory::new()->active(5)->make()->displayState())->toBe('due')
        ->and(SubscriptionFactory::new()->active(-2)->make()->displayState())->toBe('overdue')
        ->and(SubscriptionFactory::new()->cancelled()->make()->displayState())->toBe('cancelled');
});

it('summarises revenue from active subscriptions only', function () {
    $standard = PlanFactory::new()->create(['price_monthly' => 350_000, 'price_yearly' => 3_600_000]);

    SubscriptionFactory::new()->forPlan($standard->id)->active(30)->create();
    SubscriptionFactory::new()->forPlan($standard->id)->active(30, BillingCycle::Yearly)->create();
    SubscriptionFactory::new()->forPlan($standard->id)->active(-3)->create();
    SubscriptionFactory::new()->forPlan($standard->id)->trialEndingIn(3)->create();
    SubscriptionFactory::new()->forPlan($standard->id)->cancelled()->create();

    $summary = app(BillingSummary::class)->summary();

    expect($summary['mrr'])->toBe(350_000 + 300_000)
        ->and($summary['arr'])->toBe((350_000 + 300_000) * 12)
        ->and($summary['activeSubscriptions'])->toBe(2)
        ->and($summary['trial'])->toBe(1)
        ->and($summary['dueOrOverdue'])->toBe(1)
        ->and($summary['cancelled'])->toBe(1);

    expect(app(BillingSummary::class)->needsAttention())->toHaveCount(2);
});
