<?php

namespace Modules\Platform\App\Domain\Models;

/**
 * The billing messages a school's billing contact can receive (the six
 * of the invoice's life, plus the trial-ending reminder, which has no
 * invoice); persisted as the string value.
 */
enum BillingNoticeKind: string
{
    case InvoiceIssued = 'invoice_issued';
    case DueReminder = 'due_reminder';
    case TrialEnding = 'trial_ending';
    case OverdueReminder = 'overdue_reminder';
    case PaymentReceived = 'payment_received';
    case AccessStopped = 'access_stopped';
    case AccessReopened = 'access_reopened';
}
