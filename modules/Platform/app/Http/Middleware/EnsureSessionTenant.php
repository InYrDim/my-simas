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
 * Generic by design: inspects `tenant_id` on the authenticated model
 * via attributes (no Identity import — Platform never sees user
 * models). Users without a tenant_id attribute (e.g. provider staff on
 * central hosts) are untouched.
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

        if ($tenantId !== null) {
            $user = $request->user();

            $userTenantId = $user?->getAttribute('tenant_id');

            if (is_string($userTenantId) && $userTenantId !== $tenantId) {
                Auth::guard('web')->logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return $next($request);
    }
}
