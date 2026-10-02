<?php

namespace Modules\Core\App\Contracts\Exceptions;

use InvalidArgumentException;

/**
 * A notice was sent for a kind no module registered (or one whose module
 * is not active for the school).
 */
final class UnknownNoticeKindException extends InvalidArgumentException
{
    public function __construct(string $kind)
    {
        parent::__construct("Jenis pemberitahuan [{$kind}] tidak terdaftar.");
    }
}
