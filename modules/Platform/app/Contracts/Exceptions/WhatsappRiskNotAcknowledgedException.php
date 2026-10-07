<?php

namespace Modules\Platform\App\Contracts\Exceptions;

use RuntimeException;

/**
 * The school tried to link a number before accepting the warning that
 * unofficial WhatsApp can get a number restricted or banned.
 */
final class WhatsappRiskNotAcknowledgedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Setujui peringatan risiko WhatsApp lebih dulu sebelum menghubungkan nomor.');
    }
}
