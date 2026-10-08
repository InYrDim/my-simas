<?php

namespace Modules\Platform\App\Infrastructure\Mail;

/**
 * Reminder that a school's trial is about to end. There is no invoice yet:
 * the end date comes in `dueOn`.
 */
final class TrialEndingMail extends BillingMail
{
    protected function subjectLine(): string
    {
        return __('Masa uji coba SIMAS segera berakhir');
    }

    protected function textView(): string
    {
        return 'Platform::billing-trial-ending-text';
    }
}
