<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Payment;
use Modules\Platform\App\Domain\Models\PaymentStatus;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\App\Infrastructure\Modules\DefaultModuleRegistry;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;

/**
 * Write path for tenant subscriptions. Money only moves through
 * settle(); everything else records intent.
 *
 * Flow: startTrial (onboarding) → activate (issues an unpaid invoice) →
 * PaymentGateway::initiate (pending Payment) → settle (paid ⇒ invoice
 * paid, subscription active, period extended, plan modules synced) →
 * renew each period the same way.
 * Date-derived states need no job: see Subscription::displayState().
 */
final class SubscriptionManager
{
    public function __construct(
        private readonly ModuleFlagManager $flags,
        private readonly DefaultModuleRegistry $registry,
        private readonly InvoiceIssuer $invoices,
    ) {}

    public function startTrial(string $tenantId, string $planKey, ?int $days = null): Subscription
    {
        $plan = $this->selectablePlan($planKey);

        if (Subscription::query()->where('tenant_id', $tenantId)->exists()) {
            throw new BillingException('Tenant already has a subscription.');
        }

        return DB::transaction(function () use ($tenantId, $plan, $days): Subscription {
            $subscription = Subscription::query()->create([
                'tenant_id' => $tenantId,
                'plan_id' => $plan->id,
                'billing_cycle' => BillingCycle::Monthly,
                'status' => SubscriptionStatus::Trial,
                'trial_ends_at' => BillingClock::today()->addDays($days ?? (int) config('billing.trial_days', 14)),
            ]);

            $this->syncModules($subscription, $plan);

            return $subscription;
        });
    }

    public function extendTrial(Subscription $subscription, int $days): Subscription
    {
        if (! $subscription->isTrial()) {
            throw new BillingException('Only a trial can be extended.');
        }

        $base = $subscription->trial_ends_at !== null && $subscription->trial_ends_at->gte(BillingClock::today())
            ? $subscription->trial_ends_at->copy()
            : BillingClock::today();

        $subscription->forceFill(['trial_ends_at' => $base->addDays($days)])->save();

        return $subscription;
    }

    /**
     * Choose a plan and cycle and issue the invoice for the next period.
     * The subscription turns active only when that invoice is paid.
     */
    public function activate(Subscription $subscription, string $planKey, BillingCycle $cycle): Invoice
    {
        $plan = $this->selectablePlan($planKey);

        $subscription->forceFill([
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
        ])->save();

        return $this->invoices->issueActivation($subscription, $plan, $cycle, $this->nextPeriodStart($subscription));
    }

    /**
     * Issue the invoice for the next period on the current plan/cycle.
     */
    public function renew(Subscription $subscription): Invoice
    {
        if ($subscription->isTrial()) {
            throw new BillingException('A trial is activated, not renewed.');
        }

        $plan = $this->planOf($subscription);

        $dueAt = $this->continuesPaidPeriod($subscription)
            ? $subscription->current_period_end->copy()
            : BillingClock::today()->addDays((int) config('billing.invoice_due_days', 7));

        return $this->invoices->issueRenewal($subscription, $plan, $subscription->billing_cycle, $this->nextPeriodStart($subscription), $dueAt);
    }

    /**
     * Takes effect immediately for modules; no proration. The amount
     * changes from the next invoice.
     */
    public function changePlan(Subscription $subscription, string $planKey): Subscription
    {
        $plan = $this->selectablePlan($planKey);

        $subscription->forceFill(['plan_id' => $plan->id])->save();

        if ($subscription->status !== SubscriptionStatus::Cancelled) {
            $this->syncModules($subscription, $plan);
        }

        return $subscription;
    }

    /**
     * Applies from the next invoice.
     */
    public function changeCycle(Subscription $subscription, BillingCycle $cycle): Subscription
    {
        $subscription->forceFill(['billing_cycle' => $cycle])->save();

        return $subscription;
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->forceFill([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        return $subscription;
    }

    /**
     * The only door through which a payment changes an invoice and its
     * subscription. Idempotent: the invoice row is locked, and a payment
     * already settled (or an invoice already paid) is a no-op.
     *
     * A paid outcome must carry exactly the invoice amount; anything else
     * is refused and nothing is recorded as paid. Failed and expired
     * outcomes only change the Payment: the invoice stays unpaid. The real
     * period is worked out here, at payment time, from the invoice's plan
     * and cycle snapshot.
     *
     * @throws BillingException
     */
    public function settle(Payment $payment, PaymentOutcome $outcome): Payment
    {
        return DB::transaction(function () use ($payment, $outcome): Payment {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()
                ->where('tenant_id', $payment->tenant_id)
                ->lockForUpdate()
                ->findOrFail($payment->invoice_id);

            $payment->refresh();

            if ($payment->status !== PaymentStatus::Pending || $invoice->status === InvoiceStatus::Paid) {
                return $payment;
            }

            if ($outcome->status !== PaymentStatus::Paid) {
                $payment->forceFill([
                    'status' => $outcome->status,
                    'note' => $outcome->note ?? $payment->note,
                ])->save();

                return $payment;
            }

            if ($invoice->status !== InvoiceStatus::Unpaid) {
                throw new BillingException("Invoice [{$invoice->number}] is {$invoice->status->value} and cannot be paid.");
            }

            if ($outcome->amount !== $invoice->amount) {
                throw new BillingException("Payment amount does not match invoice [{$invoice->number}]: expected {$invoice->amount}.");
            }

            if (
                $outcome->externalId !== null
                && Payment::query()
                    ->where('gateway', $payment->gateway)
                    ->where('external_id', $outcome->externalId)
                    ->whereKeyNot($payment->id)
                    ->exists()
            ) {
                throw new BillingException("Payment reference [{$outcome->externalId}] has already been used.");
            }

            /** @var Subscription $subscription */
            $subscription = Subscription::query()
                ->where('tenant_id', $invoice->tenant_id)
                ->lockForUpdate()
                ->findOrFail($invoice->subscription_id);

            $start = $this->nextPeriodStart($subscription);
            $end = $start->copy()->addMonthsNoOverflow($invoice->billing_cycle->months());

            $payment->forceFill([
                'status' => PaymentStatus::Paid,
                'amount' => $outcome->amount,
                'method' => $outcome->method ?? $payment->method,
                'reference' => $outcome->reference ?? $payment->reference,
                'external_id' => $outcome->externalId ?? $payment->external_id,
                'paid_on' => $outcome->paidOn ?? BillingClock::today(),
                'paid_at' => now(),
                'confirmed_by' => $outcome->confirmedBy,
                'note' => $outcome->note ?? $payment->note,
                'meta' => array_merge($payment->meta ?? [], $outcome->meta),
            ])->save();

            $invoice->forceFill([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'period_start' => $start,
                'period_end' => $end,
            ])->save();

            $subscription->forceFill([
                'plan_id' => $invoice->plan_id,
                'billing_cycle' => $invoice->billing_cycle,
                'status' => SubscriptionStatus::Active,
                'current_period_start' => $start,
                'current_period_end' => $end,
                'cancelled_at' => null,
            ])->save();

            $this->syncModules($subscription, Plan::query()->findOrFail($invoice->plan_id));

            return $payment;
        });
    }

    /**
     * An active, unexpired subscription continues from its period end;
     * anything else starts today.
     */
    private function nextPeriodStart(Subscription $subscription): CarbonInterface
    {
        if ($this->continuesPaidPeriod($subscription)) {
            return $subscription->current_period_end->copy();
        }

        return BillingClock::today();
    }

    private function continuesPaidPeriod(Subscription $subscription): bool
    {
        return $subscription->status === SubscriptionStatus::Active
            && $subscription->current_period_end !== null
            && $subscription->current_period_end->gte(BillingClock::today());
    }

    /**
     * Enable the plan's modules and disable the other registered ones.
     * core (always active) and the onboarding modules (login depends on
     * them) are never disabled by a plan change.
     */
    private function syncModules(Subscription $subscription, Plan $plan): void
    {
        $included = $plan->modules;
        $protected = (array) config('tenancy.onboarding_modules', []);

        foreach (array_keys($this->registry->all()) as $key) {
            if ($this->registry->isAlwaysActive($key)) {
                continue;
            }

            if (in_array($key, $included, true)) {
                $this->flags->enable($subscription->tenant_id, $key);
            } elseif (! in_array($key, $protected, true)) {
                $this->flags->disable($subscription->tenant_id, $key);
            }
        }
    }

    private function planOf(Subscription $subscription): Plan
    {
        return Plan::query()->findOrFail($subscription->plan_id);
    }

    private function selectablePlan(string $planKey): Plan
    {
        $plan = Plan::query()->selectable()->where('key', $planKey)->first();

        if ($plan === null) {
            throw new BillingException("Plan [{$planKey}] does not exist or is not available.");
        }

        return $plan;
    }
}
