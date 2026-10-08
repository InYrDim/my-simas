<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * The six billing messages a school's billing contact can receive;
 * persisted as the string value.
 */
enum BillingNoticeKind: string
{
    case InvoiceIssued = 'invoice_issued';
    case DueReminder = 'due_reminder';
    case OverdueReminder = 'overdue_reminder';
    case PaymentReceived = 'payment_received';
    case AccessStopped = 'access_stopped';
    case AccessReopened = 'access_reopened';
}
