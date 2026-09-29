<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Modules\Platform\App\Http\Middleware\EnsureSessionTenant;
use Modules\Platform\App\Http\Middleware\ResolveTenant;
use Modules\Platform\App\Http\Middleware\ShareTenantContext;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Last line of defence: a session whose user belongs to a
            // different tenant than the resolved context is logged out.
            EnsureSessionTenant::class,
        ]);

        // Tenant resolution must happen before bindings and authentication
        // so route model binding and auth are tenant-scoped. ShareTenantContext
        // follows it (reads the resolved context) and must precede the Inertia
        // middleware so tenant props are registered before shared props resolve.
        // A single prepend call: successive prepends stack in reverse.
        $middleware->web(prepend: [ResolveTenant::class, ShareTenantContext::class]);

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: ResolveTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
