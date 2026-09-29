<?php

namespace Modules\Platform\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Platform\App\Contracts\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense-in-depth for session isolation across tenant hosts.
 *
 * Primary defense: session cookies are host-only (SESSION_DOMAIN null)
 * so a browser never sends a tenant A cookie to tenant B. If cookies
 * DID leak (e.g. a misconfigured shared SESSION_DOMAIN), this
 * middleware still refuses the session: an authenticated user whose
 * tenant_id disagrees with the ambient tenant context is logged out
 * before the route runs.
 *
 * Generic by design: inspects `tenant_id` and `deactivated_at` on the
 * authenticated model via attributes (no Identity import — Platform
 * never sees user models). Users without a tenant_id attribute (e.g.
 * provider staff on central hosts) are untouched.
 *
 * Deactivation convention (Fase 2 Stage 3): a NULL `deactivated_at`
 * attribute means active; any non-null value means the account was
 * deactivated and the session must end — the same fail-closed pattern
 * as the tenant_id mismatch branch. Attribute-based, so Identity (or
 * any future user-like model) only needs the column, no interface.
 */
final class EnsureSessionTenant
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->context->id();

        $user = $request->user();

        if ($tenantId !== null && $user !== null) {
            $userTenantId = $user->getAttribute('tenant_id');

            if (is_string($userTenantId) && $userTenantId !== $tenantId) {
                $this->terminateSession($request);
            } else {
                // Deactivated accounts keep no live sessions: a non-null
                // deactivated_at attribute ends the session on the next
                // request. Deliberately NOT a sessions.user_id lookup —
                // users.id repeats across tenants, so that key is
                // ambiguous (see the architecture doc's trap list).
                $deactivatedAt = $user->getAttribute('deactivated_at');

                if ($deactivatedAt !== null) {
                    $this->terminateSession($request);
                }
            }
        }

        return $next($request);
    }

    /**
     * Log out, invalidate the session, and rotate the CSRF token.
     */
    private function terminateSession(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
