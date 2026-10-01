<?php

namespace Modules\Platform\App\Contracts;

/**
 * The school a browser session belongs to. Tenant resolution reads it on
 * every request (a logged-in session's school is authoritative); this
 * contract is the one sanctioned way for another module to SET it — the
 * session key itself stays internal to Platform.
 */
interface TenantSession
{
    /**
     * Remember the school in the current session, as a login with the
     * school code would. Call it together with signing the user in:
     * a logged-in session without a school is ended on the next request.
     */
    public function remember(string $tenantId): void;
}
