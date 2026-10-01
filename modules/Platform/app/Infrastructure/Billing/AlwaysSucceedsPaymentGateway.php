<?php

namespace Modules\Platform\App\Infrastructure\Billing;

use Modules\Platform\App\Domain\Models\Invoice;

/**
 * Placeholder gateway: every charge succeeds. Real billing is deferred;
 * replace the PaymentGateway binding in PlatformServiceProvider when a
 * payment provider is chosen.
 *
 * TODO(billing): swap for a real gateway implementation.
 */
final class AlwaysSucceedsPaymentGateway implements PaymentGateway
{
    public function charge(Invoice $invoice): bool
    {
        return true;
    }
}
