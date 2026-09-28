<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use RuntimeException;

/**
 * Internal: the request host is not a central domain and matches no
 * tenant (by slug or custom domain). The middleware converts this into
 * a generic 404 — fail closed.
 */
final class TenantMissingException extends RuntimeException
{
    public function __construct(string $message = 'No tenant matches this host.')
    {
        parent::__construct($message);
    }
}
