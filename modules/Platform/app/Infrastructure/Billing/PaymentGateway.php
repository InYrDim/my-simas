<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\Payment;

/**
 * Starts a payment for an invoice. Internal seam: the gateway only
 * creates a pending Payment carrying the pay instructions (manual: the
 * provider's bank account; a real gateway: a virtual account or pay URL).
 * The invoice and subscription only change through
 * SubscriptionManager::settle(), called by the provider's confirmation
 * or, later, a gateway webhook.
 */
interface PaymentGateway
{
    public function initiate(Invoice $invoice): Payment;
}
