<?php

namespace Modules\Ppdb\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Password-reset link for an applicant account. Queued; the signed URL is
 * prebuilt by the sender.
 */
final class AccountPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Atur Ulang Kata Sandi — PPDB SIMAS'));
    }

    public function content(): Content
    {
        /** @var view-string $textView */
        $textView = 'Ppdb::account-password-reset-text';

        return new Content(
            text: $textView,
            with: ['resetUrl' => $this->resetUrl, 'recipientName' => $this->recipientName],
        );
    }
}
