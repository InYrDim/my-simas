<?php

namespace Modules\Identity\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Set-password link for a newly provisioned account (the first school
 * admin from TenantApproved, and later email invitations — Stage 10
 * reuses this exact mail). Queued; the URL is prebuilt by the sender
 * via Platform's TenantUrl contract, so the worker never needs a
 * request root and the link lands on the TENANT host.
 *
 * The text view resolves through the `Identity::` view namespace
 * (modules/Identity/mail, registered in IdentityServiceProvider).
 */
final class SetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $setPasswordUrl,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Aktivasi Akun — SIMAS'),
        );
    }

    public function content(): Content
    {
        /** @var view-string $textView */
        $textView = 'Identity::set-password-text';

        return new Content(
            text: $textView,
            with: [
                'setPasswordUrl' => $this->setPasswordUrl,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}
