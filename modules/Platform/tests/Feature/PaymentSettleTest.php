<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceKind;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Infrastructure\Billing\ManualTransferGateway;
use Modules\Platform\App\Infrastructure\Billing\PaymentGateway;
use Modules\Platform\App\Infrastructure\Billing\PaymentOutcome;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Modules\Platform\Database\Factories\PaymentFactory;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Tests\Support\FakePaymentGateway;

/**
 * SubscriptionManager::settle(): the single door from a payment to the
 * invoice and the subscription (fase 17, tahap 2).
 */
function manager(): SubscriptionManager
{
    return app(SubscriptionManager::class);
}

function activationInvoice(): Invoice
{
    $subscription = SubscriptionFactory::new()->trialEndingIn(5)->create();

    return manager()->activate($subscription, $subscription->plan->key, BillingCycle::Monthly);
}

it('binds the manual transfer gateway and reuses its open payment', function () {
    expect(app(PaymentGateway::class))->toBeInstanceOf(ManualTransferGateway::class);

    config(['billing.issuer.bank' => ['name' => 'BCA', 'account' => '1234567890', 'holder' => 'PT SIMAS']]);
    $invoice = activationInvoice();

    $first = app(PaymentGateway::class)->initiate($invoice);
    $second = app(PaymentGateway::class)->initiate($invoice);

    expect($first->status)->toBe(PaymentStatus::Pending)
        ->and($first->amount)->toBe($invoice->amount)
        ->and($first->meta['instructions'])->toBe(['bank' => 'BCA', 'account' => '1234567890', 'holder' => 'PT SIMAS'])
        ->and($second->id)->toBe($first->id)
        ->and(Payment::query()->count())->toBe(1);
});

it('settles a paid outcome once: confirming twice extends the period once', function () {
    $invoice = activationInvoice();
    $payment = app(PaymentGateway::class)->initiate($invoice);
    $outcome = PaymentOutcome::paid($invoice->amount, method: 'bank_transfer', reference: 'TRF-1', confirmedBy: 7);

    manager()->settle($payment, $outcome);
    $end = $invoice->subscription->refresh()->current_period_end->toDateString();

    $again = manager()->settle($payment, $outcome);

    expect($again->status)->toBe(PaymentStatus::Paid)
        ->and($invoice->subscription->refresh()->current_period_end->toDateString())->toBe($end)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($payment->refresh()->confirmed_by)->toBe(7)
        ->and(Payment::query()->where('status', PaymentStatus::Paid)->count())->toBe(1);
});

it('does not extend again when a second payment arrives for a paid invoice', function () {
    $invoice = activationInvoice();
    $gateway = FakePaymentGateway::paying();

    manager()->settle($gateway->initiate($invoice), $gateway->outcome($invoice));
    $end = $invoice->subscription->refresh()->current_period_end->toDateString();

    $late = manager()->settle($gateway->initiate($invoice), $gateway->outcome($invoice));

    expect($late->status)->toBe(PaymentStatus::Pending)
        ->and($invoice->subscription->refresh()->current_period_end->toDateString())->toBe($end);
});

it('rejects an amount that differs from the invoice', function (int $delta) {
    $invoice = activationInvoice();
    $payment = app(PaymentGateway::class)->initiate($invoice);

    expect(fn () => manager()->settle($payment, PaymentOutcome::paid($invoice->amount + $delta)))
        ->toThrow(BillingException::class);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->subscription->refresh()->status)->toBe(SubscriptionStatus::Trial);
})->with([
    'underpaid' => [-1],
    'overpaid' => [1],
]);

it('rejects an external id already used on the same gateway', function () {
    $first = activationInvoice();
    $second = activationInvoice();
    $gateway = FakePaymentGateway::paying();

    manager()->settle($gateway->initiate($first), $gateway->outcome($first, externalId: 'EXT-1'));

    $duplicate = $gateway->initiate($second);

    expect(fn () => manager()->settle($duplicate, $gateway->outcome($second, externalId: 'EXT-1')))
        ->toThrow(BillingException::class);

    expect($duplicate->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($second->refresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('keeps external ids unique per gateway in the database', function () {
    $invoice = activationInvoice();

    PaymentFactory::new()->forInvoice($invoice)->create(['gateway' => 'fake', 'external_id' => 'EXT-9']);

    expect(fn () => PaymentFactory::new()->forInvoice($invoice)->create(['gateway' => 'fake', 'external_id' => 'EXT-9']))
        ->toThrow(QueryException::class);

    PaymentFactory::new()->forInvoice($invoice)->create(['gateway' => 'other', 'external_id' => 'EXT-9']);

    expect(Payment::query()->count())->toBe(2);
});

it('leaves the invoice unpaid when a payment fails or expires, and allows a retry', function (FakePaymentGateway $gateway, PaymentStatus $status) {
    $invoice = activationInvoice();

    $settled = manager()->settle($gateway->initiate($invoice), $gateway->outcome($invoice));

    expect($settled->status)->toBe($status)
        ->and($invoice->refresh()->status)->toBe(InvoiceStatus::Unpaid)
        ->and($invoice->subscription->refresh()->status)->toBe(SubscriptionStatus::Trial);

    $retry = FakePaymentGateway::paying();
    manager()->settle($retry->initiate($invoice), $retry->outcome($invoice));

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Paid);
})->with([
    'failed' => [fn () => FakePaymentGateway::failing(), PaymentStatus::Failed],
    'expired' => [fn () => FakePaymentGateway::expiring(), PaymentStatus::Expired],
]);

it('does not let a late failure undo a paid payment', function () {
    $invoice = activationInvoice();
    $payment = app(PaymentGateway::class)->initiate($invoice);

    manager()->settle($payment, PaymentOutcome::paid($invoice->amount));
    manager()->settle($payment, PaymentOutcome::failed());

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('refuses to settle a voided invoice', function () {
    $invoice = activationInvoice();
    $payment = app(PaymentGateway::class)->initiate($invoice);
    manager()->changeCycle($invoice->subscription, BillingCycle::Yearly);
    manager()->activate($invoice->subscription, $invoice->subscription->plan->key, BillingCycle::Yearly);

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and(fn () => manager()->settle($payment, PaymentOutcome::paid($invoice->amount)))->toThrow(BillingException::class);
});

it('keeps at most one unpaid invoice per subscription', function () {
    $standard = PlanFactory::new()->create(['key' => 'standard']);
    $subscription = SubscriptionFactory::new()->trialEndingIn(5)->create();

    $first = manager()->activate($subscription, 'standard', BillingCycle::Monthly);
    $same = manager()->activate($subscription, 'standard', BillingCycle::Monthly);

    expect($same->id)->toBe($first->id);

    $yearly = manager()->activate($subscription, 'standard', BillingCycle::Yearly);

    expect($yearly->id)->not->toBe($first->id)
        ->and($first->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and($yearly->plan_id)->toBe($standard->id);

    $otherPlan = PlanFactory::new()->create(['key' => 'pro']);
    $pro = manager()->activate($subscription, 'pro', BillingCycle::Yearly);

    expect($pro->plan_id)->toBe($otherPlan->id)
        ->and($yearly->refresh()->status)->toBe(InvoiceStatus::Void)
        ->and(Invoice::query()->where('subscription_id', $subscription->id)->where('status', InvoiceStatus::Unpaid)->count())->toBe(1);
});

it('does not touch the unpaid invoices of other subscriptions', function () {
    $other = activationInvoice();

    activationInvoice();

    expect($other->refresh()->status)->toBe(InvoiceStatus::Unpaid);
});

it('continues the period from its end when an active subscription is paid', function () {
    $subscription = SubscriptionFactory::new()->active(10)->create();
    $end = $subscription->current_period_end->copy();
    $invoice = manager()->renew($subscription);

    // The invoice's period is only an estimate: paying later or earlier
    // does not move the real period away from the current end.
    $this->travelTo(now()->addDays(3));
    $gateway = FakePaymentGateway::paying();
    manager()->settle($gateway->initiate($invoice), $gateway->outcome($invoice));

    $subscription->refresh();

    expect($subscription->current_period_start->toDateString())->toBe($end->toDateString())
        ->and($subscription->current_period_end->toDateString())->toBe($end->copy()->addMonthNoOverflow()->toDateString())
        ->and($invoice->refresh()->period_start->toDateString())->toBe($end->toDateString());
});

it('computes the period at payment time from the invoice snapshot', function () {
    $subscription = SubscriptionFactory::new()->trialEndingIn(5)->create();
    $invoice = manager()->activate($subscription, $subscription->plan->key, BillingCycle::Yearly);
    $issuedPeriodStart = $invoice->period_start->copy();

    $this->travelTo(now()->addDays(4));
    $gateway = FakePaymentGateway::paying();
    manager()->settle($gateway->initiate($invoice), $gateway->outcome($invoice));

    $subscription->refresh();

    expect($issuedPeriodStart->toDateString())->toBe(Carbon::today()->subDays(4)->toDateString())
        ->and($subscription->current_period_start->toDateString())->toBe(Carbon::today()->toDateString())
        ->and($subscription->billing_cycle)->toBe(BillingCycle::Yearly)
        ->and($subscription->plan_id)->toBe($invoice->plan_id)
        ->and($invoice->refresh()->period_start->toDateString())->toBe(Carbon::today()->toDateString());
});

it('marks the invoice kind and due date by what it is for', function () {
    $activation = activationInvoice();
    $subscription = SubscriptionFactory::new()->active(10)->create();
    $end = $subscription->current_period_end->copy();
    $renewal = manager()->renew($subscription);

    $lapsed = SubscriptionFactory::new()->active(-5)->create();
    $lapsedRenewal = manager()->renew($lapsed);

    expect($activation->kind)->toBe(InvoiceKind::Activation)
        ->and($activation->due_at->toDateString())->toBe(Carbon::today()->addDays(7)->toDateString())
        ->and($renewal->kind)->toBe(InvoiceKind::Renewal)
        ->and($renewal->due_at->toDateString())->toBe($end->toDateString())
        ->and($lapsedRenewal->due_at->toDateString())->toBe(Carbon::today()->addDays(7)->toDateString());
});
