<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * What became of one text message handed to the school's WhatsApp.
 * `sent` means the gateway accepted it for delivery, not that it arrived.
 */
final readonly class WhatsappSendResult
{
    /**
     * @param  bool  $unavailable  the school has no linked WhatsApp: nothing was tried
     * @param  string|null  $error  why it was not sent, in words a school may read
     * @param  bool  $retryable  the gateway was down or busy: the same message may go through later
     */
    private function __construct(
        public bool $sent,
        public ?string $messageId = null,
        public bool $unavailable = false,
        public ?string $error = null,
        public bool $retryable = false,
    ) {}

    public static function sent(?string $messageId): self
    {
        return new self(sent: true, messageId: $messageId);
    }

    public static function unavailable(): self
    {
        return new self(sent: false, unavailable: true, error: 'WhatsApp sekolah belum terhubung.');
    }

    public static function failed(string $error, bool $retryable): self
    {
        return new self(sent: false, error: $error, retryable: $retryable);
    }
}
