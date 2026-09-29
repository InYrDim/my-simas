<?php

namespace Modules\Platform\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Platform\App\Contracts\TenantContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks routes as CENTRAL-ONLY: requests with a resolved tenant
 * context get a generic 404. The provider console must never render
 * on a school host (no tenant leakage, same philosophy as the
 * resolver's unknown-host 404).
 */
final class EnsureCentralHost
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->id() !== null) {
            abort(404);
        }

        return $next($request);
    }
}
