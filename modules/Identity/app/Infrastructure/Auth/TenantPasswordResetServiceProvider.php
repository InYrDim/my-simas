<?php

namespace Modules\Identity\App\Infrastructure\Auth;

use Illuminate\Auth\Passwords\PasswordResetServiceProvider;

/**
 * Swaps Laravel's PasswordBrokerManager singleton for the tenant-scoped
 * one. Registered AFTER Laravel's PasswordResetServiceProvider by
 * IdentityServiceProvider (deferred parent; our boot() replacement runs
 * first because module providers boot before deferred ones resolve).
 */
final class TenantPasswordResetServiceProvider extends PasswordResetServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('auth.password', function ($app) {
            return new TenantPasswordBrokerManager($app);
        });

        $this->app->bind('auth.password.broker', function ($app) {
            return $app->make('auth.password')->broker();
        });
    }
}
