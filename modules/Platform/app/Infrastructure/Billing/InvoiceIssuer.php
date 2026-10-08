<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceKind;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Support\BillingClock;

/**
 * Creates and voids invoices; a subscription has at most one unpaid
 * invoice. Numbers are INV-YYMM-#### (sequence per issue month); plan
 * name, cycle and amount are snapshotted.
 */
final class InvoiceIssuer
{
    /**
     * The invoice for the first paid period (after a trial or a
     * cancellation). Due a few days after it is issued.
     */
    public function issueActivation(Subscription $subscription, Plan $plan, BillingCycle $cycle, CarbonInterface $periodStart): Invoice
    {
        $dueAt = BillingClock::today()->addDays((int) config('billing.invoice_due_days', 7));

        return $this->issueOrReuse($subscription, $plan, $cycle, InvoiceKind::Activation, $periodStart, $dueAt);
    }

    /**
     * The invoice for the next period on the current plan. $dueAt is the
     * day the current paid period ends; a lapsed subscription passes a
     * fresh due date instead.
     */
    public function issueRenewal(Subscription $subscription, Plan $plan, BillingCycle $cycle, CarbonInterface $periodStart, CarbonInterface $dueAt): Invoice
    {
        return $this->issueOrReuse($subscription, $plan, $cycle, InvoiceKind::Renewal, $periodStart, $dueAt);
    }

    /**
     * The invoice for moving to a dearer plan in the middle of a paid
     * period: the price difference for the same cycle, prorated over the
     * days left (rounded up to whole rupiah). The period is today up to
     * the current period end; the current plan stays in force until paid.
     * Any open invoice of the subscription is voided first.
     *
     * @throws BillingException when the paid period has no days left
     */
    public function issueUpgrade(Subscription $subscription, Plan $from, Plan $to): Invoice
    {
        $today = BillingClock::today();
        $end = $subscription->current_period_end;
        $cycle = $subscription->billing_cycle;

        $remainingDays = $end === null ? 0 : (int) $today->diffInDays($end, false);
        $periodDays = $end === null || $subscription->current_period_start === null
            ? 0
            : (int) $subscription->current_period_start->diffInDays($end);

        if ($remainingDays < 1 || $periodDays < 1) {
            throw new BillingException('The paid period has no days left to upgrade in; renew instead.');
        }

        $amount = $this->proratedAmount($to->priceFor($cycle) - $from->priceFor($cycle), $remainingDays, $periodDays);
        $dueAt = $today->copy()->addDays((int) config('billing.invoice_due_days', 7));

        return DB::transaction(function () use ($subscription, $to, $cycle, $today, $end, $amount, $dueAt): Invoice {
            $this->voidOpen($subscription);

            return $this->issueOrReuse($subscription, $to, $cycle, InvoiceKind::Upgrade, $today, $dueAt, $amount, $end);
        });
    }

    /**
     * ceil(difference × remaining ÷ period) in whole rupiah.
     */
    public function proratedAmount(int $priceDifference, int $remainingDays, int $periodDays): int
    {
        return intdiv($priceDifference * $remainingDays + $periodDays - 1, $periodDays);
    }

    /**
     * Void every unpaid invoice of the subscription: after a plan or
     * cycle change they no longer match, so paying one must not be
     * possible.
     */
    public function voidOpen(Subscription $subscription): void
    {
        Invoice::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->where('subscription_id', $subscription->id)
            ->where('status', InvoiceStatus::Unpaid)
            ->get()
            ->each(fn (Invoice $invoice): Invoice => $this->void($invoice));
    }

    public function void(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Unpaid) {
            throw new BillingException("Invoice [{$invoice->number}] is {$invoice->status->value} and cannot be voided.");
        }

        $invoice->forceFill(['status' => InvoiceStatus::Void])->save();

        return $invoice;
    }

    /**
     * An open invoice for the same plan and cycle is reused; any other
     * open invoice of the subscription is voided before a new one is
     * issued.
     */
    private function issueOrReuse(Subscription $subscription, Plan $plan, BillingCycle $cycle, InvoiceKind $kind, CarbonInterface $periodStart, CarbonInterface $dueAt, ?int $amount = null, ?CarbonInterface $periodEnd = null): Invoice
    {
        return DB::transaction(function () use ($subscription, $plan, $cycle, $kind, $periodStart, $dueAt, $amount, $periodEnd): Invoice {
            Subscription::query()
                ->where('tenant_id', $subscription->tenant_id)
                ->lockForUpdate()
                ->findOrFail($subscription->id);

            $open = Invoice::query()
                ->where('tenant_id', $subscription->tenant_id)
                ->where('subscription_id', $subscription->id)
                ->where('status', InvoiceStatus::Unpaid)
                ->orderByDesc('id')
                ->get();

            $reusable = $open->first(
                fn (Invoice $invoice): bool => $invoice->plan_id === $plan->id && $invoice->billing_cycle === $cycle,
            );

            foreach ($open as $invoice) {
                if ($invoice->id !== $reusable?->id) {
                    $this->void($invoice);
                }
            }

            if ($reusable !== null) {
                return $reusable;
            }

            $today = BillingClock::today();

            return Invoice::query()->create([
                'number' => $this->nextNumber($today),
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'billing_cycle' => $cycle,
                'kind' => $kind,
                'amount' => $amount ?? $plan->priceFor($cycle),
                'status' => InvoiceStatus::Unpaid,
                'issued_at' => $today,
                'due_at' => $dueAt,
                'period_start' => $periodStart,
                'period_end' => $periodEnd ?? $periodStart->copy()->addMonthsNoOverflow($cycle->months()),
            ]);
        });
    }

    private function nextNumber(Carbon $issuedOn): string
    {
        $prefix = 'INV-'.$issuedOn->format('ym').'-';

        $last = Invoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
