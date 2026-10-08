<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\App\Infrastructure\Modules\DefaultModuleRegistry;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;

/**
 * Write path for tenant subscriptions. Money only moves through
 * payInvoice() → PaymentGateway; everything else records intent.
 *
 * Flow: startTrial (onboarding) → activate (issues an unpaid invoice) →
 * payInvoice (gateway ok ⇒ invoice paid, subscription active, period
 * extended, plan modules synced) → renew each period the same way.
 * Date-derived states need no job: see Subscription::displayState().
 */
final class SubscriptionManager
{
    public function __construct(
        private readonly ModuleFlagManager $flags,
        private readonly DefaultModuleRegistry $registry,
        private readonly InvoiceIssuer $invoices,
        private readonly PaymentGateway $gateway,
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

        return $this->invoices->issue($subscription, $plan, $cycle, $this->nextPeriodStart($subscription));
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

        return $this->invoices->issue($subscription, $plan, $subscription->billing_cycle, $this->nextPeriodStart($subscription));
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
     * Charge the invoice through the gateway. Returns whether it was
     * paid; on a declined charge nothing changes.
     */
    public function payInvoice(Invoice $invoice): bool
    {
        if ($invoice->status !== InvoiceStatus::Unpaid) {
            throw new BillingException("Invoice [{$invoice->number}] is {$invoice->status->value} and cannot be paid.");
        }

        if (! $this->gateway->charge($invoice)) {
            return false;
        }

        DB::transaction(function () use ($invoice): void {
            /** @var Subscription $subscription */
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($invoice->subscription_id);

            $start = $this->nextPeriodStart($subscription);
            $end = $start->copy()->addMonthsNoOverflow($invoice->billing_cycle->months());

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
        });

        return true;
    }

    /**
     * An active, unexpired subscription continues from its period end;
     * anything else starts today.
     */
    private function nextPeriodStart(Subscription $subscription): CarbonInterface
    {
        if (
            $subscription->status === SubscriptionStatus::Active
            && $subscription->current_period_end !== null
            && $subscription->current_period_end->gte(BillingClock::today())
        ) {
            return $subscription->current_period_end->copy();
        }

        return BillingClock::today();
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
