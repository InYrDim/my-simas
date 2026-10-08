<?php

namespace Modules\Platform\App\Contracts;

use Modules\Platform\App\Contracts\DTOs\BillingOverview;
use Modules\Platform\App\Contracts\DTOs\InvoiceFile;
use Modules\Platform\App\Contracts\DTOs\InvoiceSummary;
use Modules\Platform\App\Contracts\DTOs\PaymentInstructions;
use Modules\Platform\App\Contracts\DTOs\PlanChangeOutcome;
use Modules\Platform\App\Contracts\DTOs\PlanOffer;
use Modules\Platform\App\Contracts\Exceptions\BillingActionRefusedException;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;

/**
 * The school's own view of its subscription and what it may do about it:
 * see the plan, usage and invoices, subscribe, change plan, and pay.
 *
 * Every method works on the ambient tenant and fails closed without one.
 * An invoice is named by its number and must belong to this school, or the
 * call refuses as not found. Nothing here moves money: paying a transfer is
 * confirmed by the provider.
 *
 * Whether the signed-in user may use this is the caller's concern
 * (`platform.billing.view` to see, `platform.billing.pay` to act); this
 * contract does not check permissions.
 *
 * @throws TenantNotSetException on every method, without a tenant context
 */
interface TenantBilling
{
    public function overview(): BillingOverview;

    /**
     * The school's invoices, newest first.
     *
     * @return list<InvoiceSummary>
     */
    public function invoices(): array;

    /**
     * The public plans the school may choose, with what each would cost it.
     *
     * @return list<PlanOffer>
     */
    public function offers(): array;

    /**
     * Choose a public plan and cycle (`monthly` or `yearly`) and get the
     * invoice for it. Only while the school is on a trial or its
     * subscription was cancelled.
     *
     * @throws BillingActionRefusedException
     */
    public function subscribe(string $planKey, string $cycle): InvoiceSummary;

    /**
     * Start paying an unpaid invoice by bank transfer. Asking again reuses
     * the payment already waiting.
     *
     * @throws BillingActionRefusedException
     */
    public function startPayment(string $invoiceNumber): PaymentInstructions;

    /**
     * Tell the provider about a transfer already made. The payment stays
     * pending until the provider confirms it.
     *
     * @param  string  $transferredOn  `Y-m-d`, not in the future
     *
     * @throws BillingActionRefusedException
     */
    public function reportTransfer(string $invoiceNumber, string $transferredOn, string $bank, string $senderName, ?string $reference = null): void;

    /**
     * Move to another public plan: at once during a trial, an invoice for
     * the prorated difference for a dearer plan, or a downgrade scheduled
     * for the end of the paid period.
     *
     * @throws BillingActionRefusedException
     */
    public function changePlan(string $planKey): PlanChangeOutcome;

    /**
     * Drop a downgrade that was scheduled.
     *
     * @throws BillingActionRefusedException
     */
    public function cancelScheduledChange(): void;

    /**
     * Pay monthly or yearly from the next invoice on.
     *
     * @throws BillingActionRefusedException
     */
    public function changeCycle(string $cycle): void;

    /**
     * @throws BillingActionRefusedException
     */
    public function invoicePdf(string $invoiceNumber): InvoiceFile;
}
