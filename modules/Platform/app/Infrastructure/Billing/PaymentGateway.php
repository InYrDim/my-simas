<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Modules\Platform\App\Domain\Models\Invoice;

/**
 * Charges an invoice. Internal seam: the real payment provider replaces
 * the binding later; callers only care whether the charge succeeded.
 */
interface PaymentGateway
{
    public function charge(Invoice $invoice): bool;
}
