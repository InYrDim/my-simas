<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Modules\Platform\App\Contracts\TenantSession;
use Modules\Platform\App\Http\Middleware\ResolveTenant;

/**
 * Default TenantSession: writes the key ResolveTenant reads.
 */
final class SessionTenantSession implements TenantSession
{
    public function remember(string $tenantId): void
    {
        // Resolved per call: the session store belongs to the request.
        session()->put(ResolveTenant::SESSION_KEY, $tenantId);
    }
}
