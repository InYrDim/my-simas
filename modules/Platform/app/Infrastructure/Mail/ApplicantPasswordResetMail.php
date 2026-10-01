<?php

namespace Modules\Platform\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Password-reset link for an applicant account. Queued; the signed URL
 * is prebuilt by the sender.
 */
final class ApplicantPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Atur Ulang Kata Sandi — SIMAS'),
        );
    }

    public function content(): Content
    {
        /** @var view-string $textView */
        $textView = 'Platform::applicant-password-reset-text';

        return new Content(
            text: $textView,
            with: [
                'resetUrl' => $this->resetUrl,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}
