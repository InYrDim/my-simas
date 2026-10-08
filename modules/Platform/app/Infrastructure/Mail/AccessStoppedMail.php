<?php

namespace Modules\Platform\App\Infrastructure\Mail;

/**
 * The school was suspended for an unpaid invoice.
 */
final class AccessStoppedMail extends BillingMail
{
    protected function subjectLine(): string
    {
        return __('Akses :school dihentikan — SIMAS', ['school' => $this->schoolName]);
    }

    protected function textView(): string
    {
        return 'Platform::billing-access-stopped-text';
    }
}
