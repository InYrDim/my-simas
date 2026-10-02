<?php

namespace Modules\Platform\App\Contracts\Exceptions;

use RuntimeException;

/**
 * The school has no WhatsApp it may use: never approved, rejected, or
 * disabled by the provider.
 */
final class WhatsappUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('WhatsApp sekolah belum disetujui atau sedang dinonaktifkan.');
    }
}
