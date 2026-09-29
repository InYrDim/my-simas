<?php

namespace Modules\Identity\App\Infrastructure\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Password reset link email (queued). The URL is prebuilt by the User
 * model via Platform's TenantUrl contract, so the queued mail never
 * depends on the request root (workers have none).
 *
 * Fase 2 decision: transactional email ships as a plain Mailable via
 * Mail::to()->queue() — no Notification machinery (that layer, with
 * database/broadcast channels and per-user preferences, comes later
 * if ever needed).
 */
final class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $resetUrl,
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
        $textView = 'modules/Identity/mail/reset-password-text';

        return new Content(
            text: $textView,
            with: ['resetUrl' => $this->resetUrl],
        );
    }
}
