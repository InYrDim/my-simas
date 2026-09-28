<?php

namespace Modules\Platform\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Platform\App\Contracts\TenantModules;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Route middleware alias "module:{key}": rejects the request with 403
 * when the module is not enabled for the current tenant. Runs after
 * ResolveTenant (needs tenant context). core is always active, so
 * module:core never blocks — registering core as always-active is the
 * module's own declaration, not Platform hardcoding.
 *
 * Fail closed: unknown keys are treated as disabled (403).
 */
final class EnsureModuleActive
{
    public function __construct(
        private readonly TenantModules $modules,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! $this->modules->isEnabled($module)) {
            throw new AccessDeniedHttpException;
        }

        return $next($request);
    }
}
