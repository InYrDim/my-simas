<?php

namespace Modules\Platform\App\Infrastructure\Mail;

/**
 * Receipt: the payment was confirmed. The PDF attached is the same
 * document as the invoice, stamped Lunas.
 */
final class PaymentReceivedMail extends BillingMail
{
    protected function subjectLine(): string
    {
        return __('Pembayaran invoice :number diterima — SIMAS', ['number' => $this->invoiceNumber]);
    }

    protected function textView(): string
    {
        return 'Platform::billing-payment-received-text';
    }

    protected function attachesInvoice(): bool
    {
        return true;
    }
}
