<?php

namespace Modules\Platform\App\Contracts\DTOs;

/**
 * Where a school's WhatsApp stands: its request, the provider's decision
 * and the link to a number. Safe to hand to a page: it carries no API key,
 * key id or gateway session id.
 */
final readonly class WhatsappState
{
    public const STAGE_NONE = 'none';

    public const STAGE_PENDING = 'pending';

    public const STAGE_REJECTED = 'rejected';

    public const STAGE_DISABLED = 'disabled';

    public const STAGE_ACTIVE = 'active';

    /**
     * @param  string  $stage  one of the STAGE_* values
     * @param  string|null  $connection  the gateway's session status (`created`, `qr_ready`, `ready`, ...), only while active
     * @param  string|null  $note  why the provider rejected or disabled it
     * @param  string|null  $qrCode  PNG data URL to scan, only while the gateway shows one
     * @param  string|null  $requestedAt  ISO 8601
     */
    public function __construct(
        public string $stage,
        public ?string $connection = null,
        public ?string $phone = null,
        public ?string $pushName = null,
        public ?string $note = null,
        public ?string $qrCode = null,
        public ?string $lastError = null,
        public ?string $requestedAt = null,
    ) {}

    /**
     * Approved and linked to a number: messages can be sent.
     */
    public function connected(): bool
    {
        return $this->stage === self::STAGE_ACTIVE && $this->connection === 'ready';
    }
}
