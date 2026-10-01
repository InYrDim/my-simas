<?php

namespace Modules\Platform\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation from the provider to register a school: a signed link to
 * set a password for an applicant account the provider created. Queued;
 * the URL is prebuilt by the sender.
 */
final class ApplicantInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $invitationUrl,
        public readonly string $recipientName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Undangan Mendaftarkan Sekolah — SIMAS'),
        );
    }

    public function content(): Content
    {
        /** @var view-string $textView */
        $textView = 'Platform::applicant-invitation-text';

        return new Content(
            text: $textView,
            with: [
                'invitationUrl' => $this->invitationUrl,
                'recipientName' => $this->recipientName,
            ],
        );
    }
}
