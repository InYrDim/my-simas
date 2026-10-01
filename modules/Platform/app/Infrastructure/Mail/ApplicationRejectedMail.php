<?php

namespace Modules\Platform\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an applicant their application was not accepted yet, with the
 * provider's note and the way back to correct and resubmit it. Queued.
 */
final class ApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $schoolName,
        public readonly ?string $note,
        public readonly string $onboardingUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Pengajuan Sekolah Perlu Diperbaiki — SIMAS'),
        );
    }

    public function content(): Content
    {
        /** @var view-string $textView */
        $textView = 'Platform::application-rejected-text';

        return new Content(
            text: $textView,
            with: [
                'recipientName' => $this->recipientName,
                'schoolName' => $this->schoolName,
                'note' => $this->note,
                'onboardingUrl' => $this->onboardingUrl,
            ],
        );
    }
}
