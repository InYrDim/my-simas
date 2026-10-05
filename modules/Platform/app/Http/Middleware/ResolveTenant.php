<?php

namespace Modules\Platform\App\Http\Middleware;

use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Tenancy\DefaultTenantContext;
use Modules\Platform\App\Infrastructure\Tenancy\SchoolCodeTenantResolver;
use Modules\Platform\App\Infrastructure\Tenancy\TenantMissingException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Resolves the tenant from the school code, not the host. Runs AFTER
 * the session starts and before bindings/auth (priority list). Order:
 *
 * - console host            → never a tenant (provider console)
 * - authenticated session   → the tenant remembered in the session is
 *                             authoritative; a `school` input cannot
 *                             switch a logged-in user to another school
 * - guest + `school` input  → resolved and remembered in the session
 *                             (login form field, emailed links, or the
 *                             school's own login address `/{school}/login`)
 * - guest + session tenant  → the school chosen earlier in this session
 *
 * Fails closed: an unknown or malformed code leaves the request with NO
 * context (the controllers answer generically); a suspended tenant
 * gets 403. The terminator clears the context at the very end of the
 * request so long-running servers (Octane) never leak tenant state.
 */
final class ResolveTenant
{
    /**
     * Session key holding the chosen/authenticated school's tenant id.
     */
    public const SESSION_KEY = 'tenant_id';

    public function __construct(
        private readonly DefaultTenantContext $context,
        private readonly SchoolCodeTenantResolver $resolver,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always start from a clean slate — no context leaks between
        // requests on the same worker.
        $this->context->forget();

        if ($this->isConsoleHost($request)) {
            return $next($request);
        }

        $code = $this->schoolCode($request);

        if ($code !== null) {
            $this->adoptSchool($request, $code);
        } elseif ($this->hasLoggedInSession($request)) {
            // Logged in but the session names no school: nothing can
            // scope the user lookup, so the session cannot be trusted.
            $this->endSession($request);
        }

        return $next($request);
    }

    /**
     * Clear the context after the response has been sent (terminator).
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->context->forget();
    }

    private function isConsoleHost(Request $request): bool
    {
        return strtolower($request->getHost()) === strtolower((string) config('tenancy.console_domain'));
    }

    /**
     * The code to resolve for this request, or null for "no school".
     */
    private function schoolCode(Request $request): ?string
    {
        $session = $request->hasSession() ? $request->session() : null;

        $remembered = $session?->get(self::SESSION_KEY);
        $remembered = is_string($remembered) && $remembered !== '' ? $remembered : null;

        if ($this->hasLoggedInSession($request)) {
            return $remembered;
        }

        $submitted = $request->input('school');

        if (is_string($submitted) && trim($submitted) !== '') {
            return $submitted;
        }

        $inPath = $request->route('school');

        if (is_string($inPath) && trim($inPath) !== '') {
            return $inPath;
        }

        return $remembered;
    }

    /**
     * Whether the session carries a web-guard login. Reads the session
     * key only — resolving the user would hit the tenant-scoped query.
     */
    private function hasLoggedInSession(Request $request): bool
    {
        $guard = Auth::guard('web');

        return $guard instanceof SessionGuard
            && $request->hasSession()
            && $request->session()->has($guard->getName());
    }

    /**
     * Drop the whole session without touching the user provider (which
     * is tenant-scoped and has no context here).
     */
    private function endSession(Request $request): void
    {
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            $guard->forgetUser();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function adoptSchool(Request $request, string $code): void
    {
        try {
            $tenant = $this->resolver->resolve($code);
        } catch (TenantMissingException) {
            // Unknown school (or one deleted mid-session): a guest just
            // has no school; a logged-in session is ended outright.
            $this->hasLoggedInSession($request)
                ? $this->endSession($request)
                : ($request->hasSession() && $request->session()->forget(self::SESSION_KEY));

            return;
        }

        $this->context->adopt($tenant);

        if ($tenant->status === TenantStatus::Suspended) {
            throw new AccessDeniedHttpException;
        }

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $tenant->id);
        }
    }
}
