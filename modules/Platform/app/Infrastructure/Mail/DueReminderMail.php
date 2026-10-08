<?php

namespace Modules\Platform\App\Infrastructure\Mail;

/**
 * Reminder that an invoice falls due soon.
 */
final class DueReminderMail extends BillingMail
{
    protected function subjectLine(): string
    {
        return __('Pengingat: invoice :number segera jatuh tempo — SIMAS', ['number' => $this->invoiceNumber]);
    }

    protected function textView(): string
    {
        return 'Platform::billing-due-reminder-text';
    }
}
