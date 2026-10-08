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
     * @param  bool  $throttled  held back on purpose (pacing, daily limit, paused after failures): nothing was tried, and waiting is not a failure
     * @param  int|null  $retryAfter  seconds to wait before trying again, only when `$throttled`
     */
    private function __construct(
        public bool $sent,
        public ?string $messageId = null,
        public bool $unavailable = false,
        public ?string $error = null,
        public bool $retryable = false,
        public bool $throttled = false,
        public ?int $retryAfter = null,
    ) {}

    public static function sent(?string $messageId): self
    {
        return new self(sent: true, messageId: $messageId);
    }

    public static function unavailable(): self
    {
        return new self(sent: false, unavailable: true, error: 'WhatsApp sekolah belum terhubung.');
    }

    /**
     * The school is suspended: nothing was tried and nothing will be, so
     * the message is closed as not sent (not failed, not retried).
     */
    public static function suspended(): self
    {
        return new self(sent: false, unavailable: true, error: 'Pengiriman dihentikan sementara karena akses sekolah ditangguhkan.');
    }

    /**
     * Held back to protect the school's number. The same message should
     * be offered again after `$retryAfterSeconds`; this is not a failure.
     */
    public static function throttled(int $retryAfterSeconds, string $reason): self
    {
        return new self(
            sent: false,
            error: $reason,
            retryable: true,
            throttled: true,
            retryAfter: max(1, $retryAfterSeconds),
        );
    }

    public static function failed(string $error, bool $retryable): self
    {
        return new self(sent: false, error: $error, retryable: $retryable);
    }
}
