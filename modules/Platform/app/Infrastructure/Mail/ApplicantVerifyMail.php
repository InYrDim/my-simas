<?php

namespace Modules\Platform\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email-verification link for a new applicant account. Queued; the
 * signed URL is prebuilt by the sender during the request, so the
 * worker never needs a request root.
 *
 * The text view resolves through the `Platform::` view namespace
 * (modules/Platform/mail, registered in PlatformServiceProvider).
 */
final class ApplicantVerifyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $verifyUrl,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Verifikasi Email — SIMAS'),
        );
    }

    public function content(): Content
    {
        /** @var view-string $textView */
        $textView = 'Platform::applicant-verify-text';

        return new Content(
            text: $textView,
            with: [
                'verifyUrl' => $this->verifyUrl,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}
