<?php

namespace Modules\Platform\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Contracts\TenantNavigation;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the tenant context with the Inertia frontend:
 *
 * - `tenant`:  { name, slug, timezone } — null on central hosts
 * - `modules`: active module keys for the current tenant
 * - `tenantNav`: sidebar entries the signed-in user may see (lazy)
 *
 * Runs AFTER ResolveTenant (reads its context) and BEFORE
 * HandleInertiaRequests (whose share() merges with these props).
 * Deliberately opaque: the frontend never sees tenant ids, status
 * enums, or any other Platform internals.
 */
final class ShareTenantContext
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly TenantModules $modules,
        private readonly ModuleRegistry $registry,
        private readonly TenantNavigation $navigation,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share([
            'tenant' => $this->sharedTenant(),
            'modules' => $this->sharedModules(),
            // Lazy: the Gate needs the signed-in user, resolved at render.
            'tenantNav' => fn (): array => $this->navigation->forCurrentUser(),
        ]);

        return $next($request);
    }

    /**
     * @return array{name: string, slug: string, timezone: string}|null
     */
    private function sharedTenant(): ?array
    {
        $tenant = $this->context->current();

        if ($tenant === null) {
            return null;
        }

        return [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'timezone' => $tenant->timezone,
        ];
    }

    /**
     * Module keys active for the current tenant, sorted for stable
     * prop output. Central requests (no context) share an empty list.
     *
     * @return list<string>
     */
    private function sharedModules(): array
    {
        if ($this->context->id() === null) {
            return [];
        }

        $keys = [];

        foreach (array_keys($this->registry->all()) as $key) {
            if ($this->modules->isEnabled($key)) {
                $keys[] = $key;
            }
        }

        sort($keys);

        return $keys;
    }
}
