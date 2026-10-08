<?php

namespace Modules\Platform\App\Infrastructure\Mail;

/**
 * An invoice is past due; access continues until the grace period ends.
 */
final class OverdueReminderMail extends BillingMail
{
    protected function subjectLine(): string
    {
        return __('Invoice :number belum dibayar — SIMAS', ['number' => $this->invoiceNumber]);
    }

    protected function textView(): string
    {
        return 'Platform::billing-overdue-reminder-text';
    }
}
