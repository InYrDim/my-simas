<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Modules\Identity\App\Http\Middleware\RequirePasswordChange;
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
            // Tenant resolution reads the session (school code / logged-in
            // school), so it runs after StartSession — see the priority
            // list below. ShareTenantContext follows it.
            ResolveTenant::class,
            ShareTenantContext::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            // Last line of defence: a session whose user belongs to a
            // different tenant than the resolved context is logged out.
            EnsureSessionTenant::class,
            // An account whose password someone else chose reaches only
            // the change-password page until it has set its own.
            RequirePasswordChange::class,
        ]);

        // Tenant resolution must happen right after the session starts and
        // before authentication, so the guard's user lookup is tenant-scoped.
        $middleware->appendToPriorityList(
            after: StartSession::class,
            append: ResolveTenant::class,
        );

        // Auth redirects are guard-aware: tenant guests land on the
        // tenant login, provider guests on the console login. The platform.*
        // routes are bound to the console host, so their generated absolute
        // URLs always point at it.
        $middleware->redirectGuestsTo(function () {
            if (strtolower(request()->getHost()) === config('tenancy.console_domain')) {
                return route('platform.login');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(function () {
            if (strtolower(request()->getHost()) === config('tenancy.console_domain')) {
                return route('platform.home');
            }

            return route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
