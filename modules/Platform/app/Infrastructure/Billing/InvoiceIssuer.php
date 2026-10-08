<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Domain\Exceptions\BillingException;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Support\BillingClock;

/**
 * Creates and voids invoices. Numbers are INV-YYMM-#### (sequence per
 * issue month); plan name, cycle and amount are snapshotted.
 */
final class InvoiceIssuer
{
    public function issue(Subscription $subscription, Plan $plan, BillingCycle $cycle, CarbonInterface $periodStart): Invoice
    {
        $today = BillingClock::today();

        return Invoice::query()->create([
            'number' => $this->nextNumber($today),
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'billing_cycle' => $cycle,
            'amount' => $plan->priceFor($cycle),
            'status' => InvoiceStatus::Unpaid,
            'issued_at' => $today,
            'due_at' => $today->copy()->addDays((int) config('billing.invoice_due_days', 7)),
            'period_start' => $periodStart,
            'period_end' => $periodStart->copy()->addMonthsNoOverflow($cycle->months()),
        ]);
    }

    public function void(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Unpaid) {
            throw new BillingException("Invoice [{$invoice->number}] is {$invoice->status->value} and cannot be voided.");
        }

        $invoice->forceFill(['status' => InvoiceStatus::Void])->save();

        return $invoice;
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
