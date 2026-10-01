<?php

namespace Modules\Platform\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when an APPROVED applicant asks for a password reset on the
 * applicant pages: their password lives on the school admin account, so
 * the mail points at the school's own reset page (with the school code).
 * Queued; the URL is prebuilt by the sender.
 */
final class ApplicantSchoolResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $schoolResetUrl,
        public readonly string $recipientName,
        public readonly string $schoolCode,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Atur Ulang Kata Sandi Sekolah — SIMAS'),
        );
    }

    public function content(): Content
    {
        /** @var view-string $textView */
        $textView = 'Platform::applicant-school-reset-text';

        return new Content(
            text: $textView,
            with: [
                'schoolResetUrl' => $this->schoolResetUrl,
                'recipientName' => $this->recipientName,
                'schoolCode' => $this->schoolCode,
            ],
        );
    }
}
