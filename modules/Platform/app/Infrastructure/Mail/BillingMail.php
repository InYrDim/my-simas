<?php

namespace Modules\Platform\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Modules\Platform\App\Domain\Models\BillingNotice;
use Modules\Platform\App\Domain\Models\BillingNoticeStatus;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Infrastructure\Billing\InvoiceDocument;
use Throwable;

/**
 * Shared shape of the six billing mails: queued, text only, built from
 * plain values the sender prepared (amounts and dates already formatted).
 * The notice id travels in a header so the MessageSent listener can mark
 * the notice sent, and a failed job marks it failed. Mails that carry the
 * invoice PDF render it at send time from `invoiceId`, so a receipt
 * always shows the status it has when it is sent.
 */
abstract class BillingMail extends Mailable
{
    use Queueable, SerializesModels;

    public const NOTICE_HEADER = 'X-Billing-Notice';

    public function __construct(
        public readonly string $recipientName,
        public readonly string $schoolName,
        public readonly int $noticeId,
        public readonly string $issuerName,
        public readonly ?string $issuerContact = null,
        public readonly ?int $invoiceId = null,
        public readonly ?string $invoiceNumber = null,
        public readonly ?string $amount = null,
        public readonly ?string $dueOn = null,
        public readonly ?string $periodLabel = null,
        public readonly ?string $graceEndsOn = null,
    ) {}

    abstract protected function subjectLine(): string;

    /**
     * @return view-string
     */
    abstract protected function textView(): string;

    protected function attachesInvoice(): bool
    {
        return false;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function headers(): Headers
    {
        return new Headers(text: [self::NOTICE_HEADER => (string) $this->noticeId]);
    }

    public function content(): Content
    {
        return new Content(
            text: $this->textView(),
            with: [
                'recipientName' => $this->recipientName,
                'schoolName' => $this->schoolName,
                'issuerName' => $this->issuerName,
                'issuerContact' => $this->issuerContact,
                'invoiceNumber' => $this->invoiceNumber,
                'amount' => $this->amount,
                'dueOn' => $this->dueOn,
                'periodLabel' => $this->periodLabel,
                'graceEndsOn' => $this->graceEndsOn,
                'bank' => (array) config('billing.issuer.bank', []),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->attachesInvoice() || $this->invoiceId === null) {
            return [];
        }

        $invoice = Invoice::query()->find($this->invoiceId);

        if ($invoice === null) {
            return [];
        }

        $document = app(InvoiceDocument::class);

        return [
            Attachment::fromData(fn (): string => $document->render($invoice), $document->filename($invoice))
                ->withMime('application/pdf'),
        ];
    }

    /**
     * Called by the queue when the send job fails for good.
     */
    public function failed(Throwable $exception): void
    {
        BillingNotice::query()->whereKey($this->noticeId)->update([
            'status' => BillingNoticeStatus::Failed,
            'error' => mb_substr($exception->getMessage(), 0, 500),
        ]);
    }
}
