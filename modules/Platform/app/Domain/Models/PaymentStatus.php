<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * Payment status. Only Paid changes the invoice; Failed and Expired leave
 * it unpaid so a new attempt can be made.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
}
