<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
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
    private const NUMBER_ATTEMPTS = 5;

    public function __construct(
        private readonly BillingNotifier $notifier,
    ) {}

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
    private function issueOrReuse(Subscription $subscription, Plan $plan, BillingCycle $cycle, InvoiceKind $kind, CarbonInterface $periodStart, CarbonInterface $dueAt): Invoice
    {
        return DB::transaction(function () use ($subscription, $plan, $cycle, $kind, $periodStart, $dueAt): Invoice {
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

            $invoice = $this->createNumbered([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'billing_cycle' => $cycle,
                'kind' => $kind,
                'amount' => $plan->priceFor($cycle),
                'status' => InvoiceStatus::Unpaid,
                'issued_at' => $today,
                'due_at' => $dueAt,
                'period_start' => $periodStart,
                'period_end' => $periodStart->copy()->addMonthsNoOverflow($cycle->months()),
            ], $today);

            $this->notifier->invoiceIssued($invoice);

            return $invoice;
        });
    }

    /**
     * Insert with the next INV-YYMM-#### number. The subscription lock does
     * not serialise two different schools, so a clash on the unique number
     * column is retried (in its own savepoint) with a fresh number.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createNumbered(array $attributes, Carbon $issuedOn): Invoice
    {
        $attempts = 0;

        while (true) {
            try {
                return DB::transaction(fn (): Invoice => Invoice::query()->create([
                    'number' => $this->nextNumber($issuedOn),
                    ...$attributes,
                ]));
            } catch (UniqueConstraintViolationException $exception) {
                if (++$attempts >= self::NUMBER_ATTEMPTS) {
                    throw $exception;
                }
            }
        }
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
