<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use RuntimeException;

/**
 * The OpenWA gateway refused a call, could not be reached, or is not
 * configured. The message names the call and the status only — never a
 * key, a header or the response body.
 */
final class OpenWaException extends RuntimeException
{
    /**
     * @param  int|null  $status  the HTTP status, or null when there was no answer
     */
    public function __construct(string $message, public readonly ?int $status = null)
    {
        parent::__construct($message);
    }

    /**
     * Whether trying the same call again later can succeed: the gateway
     * was down, overloaded or unreachable.
     */
    public function retryable(): bool
    {
        return $this->status === null || $this->status === 429 || $this->status >= 500;
    }

    /**
     * The same trouble in words a school may read: no call, no session
     * id, nothing of the gateway's setup.
     */
    public function forSchool(): string
    {
        return $this->status === null
            ? 'Gateway WhatsApp tidak bisa dihubungi. Coba lagi beberapa saat lagi.'
            : "Gateway WhatsApp menjawab galat {$this->status}. Coba lagi beberapa saat lagi.";
    }
}
