<?php

namespace Modules\Platform\App\Infrastructure\Mail;

/**
 * A new invoice for the school, with the invoice PDF attached.
 */
final class InvoiceIssuedMail extends BillingMail
{
    protected function subjectLine(): string
    {
        return __('Invoice :number — SIMAS', ['number' => $this->invoiceNumber]);
    }

    protected function textView(): string
    {
        return 'Platform::billing-invoice-issued-text';
    }

    protected function attachesInvoice(): bool
    {
        return true;
    }
}
