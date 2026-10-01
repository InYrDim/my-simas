<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Invoice status. Nothing is hard-deleted: a mistaken invoice is voided.
 */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Void = 'void';
}
