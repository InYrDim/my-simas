<?php

namespace Modules\Platform\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Platform\App\Domain\Models\TenantStatus;
use Modules\Platform\App\Infrastructure\Tenancy\DefaultTenantContext;
use Modules\Platform\App\Infrastructure\Tenancy\SubdomainTenantResolver;
use Modules\Platform\App\Infrastructure\Tenancy\TenantMissingException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Resolves the tenant from the request host. Runs before bindings and
 * auth (priority list). Fails closed:
 *
 * - central domain         → no context (central request)
 * - unknown host           → generic 404 (no tenant leakage)
 * - suspended tenant       → 403
 *
 * The terminator clears the context at the very end of the request so
 * long-running servers (Octane) never leak tenant state.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly DefaultTenantContext $context,
        private readonly SubdomainTenantResolver $resolver,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Always start from a clean slate — no context leaks between
        // requests on the same worker.
        $this->context->forget();

        $host = $request->getHost();

        try {
            $tenant = $this->resolver->resolve($host);
        } catch (TenantMissingException) {
            throw new NotFoundHttpException;
        }

        if ($tenant !== null) {
            $this->context->adopt($tenant);

            if ($tenant->status === TenantStatus::Suspended) {
                throw new AccessDeniedHttpException;
            }
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
}
