<?php

namespace Modules\Platform\App\Infrastructure\Mail;

/**
 * The school was reopened after a payment.
 */
final class AccessReopenedMail extends BillingMail
{
    protected function subjectLine(): string
    {
        return __('Akses :school dibuka kembali — SIMAS', ['school' => $this->schoolName]);
    }

    protected function textView(): string
    {
        return 'Platform::billing-access-reopened-text';
    }
}
