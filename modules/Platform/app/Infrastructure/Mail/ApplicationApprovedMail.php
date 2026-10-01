<?php

namespace Modules\Platform\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an applicant their school was approved: the school code they log
 * in with, the plan and the trial end. Queued; every value is a plain
 * string prebuilt by the sender.
 */
final class ApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $schoolName,
        public readonly string $schoolCode,
        public readonly ?string $planName,
        public readonly ?string $trialEndsOn,
        public readonly string $loginUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Sekolah Anda Disetujui — SIMAS'),
        );
    }

    public function content(): Content
    {
        /** @var view-string $textView */
        $textView = 'Platform::application-approved-text';

        return new Content(
            text: $textView,
            with: [
                'recipientName' => $this->recipientName,
                'schoolName' => $this->schoolName,
                'schoolCode' => $this->schoolCode,
                'planName' => $this->planName,
                'trialEndsOn' => $this->trialEndsOn,
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
