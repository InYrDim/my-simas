<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use RuntimeException;

/**
 * Internal: the school code matches no tenant. The middleware leaves
 * the request without tenant context (callers answer generically) —
 * fail closed.
 */
final class TenantMissingException extends RuntimeException
{
    public function __construct(string $message = 'No tenant matches this school code.')
    {
        parent::__construct($message);
    }
}
