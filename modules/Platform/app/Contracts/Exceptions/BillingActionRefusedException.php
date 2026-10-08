<?php

namespace Modules\Platform\App\Contracts\Exceptions;

use RuntimeException;

/**
 * A billing action a school asked for was refused. The message is meant
 * for the school's own screen (Indonesian, no internals). `notFound` is
 * set when the invoice asked for is not this school's: treat it as a 404.
 */
final class BillingActionRefusedException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $notFound = false)
    {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('Invoice tidak ditemukan.', notFound: true);
    }
}
