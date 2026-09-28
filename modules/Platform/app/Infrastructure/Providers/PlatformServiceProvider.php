<?php

namespace Modules\Platform\App\Infrastructure\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Http\Middleware\ResolveTenant;
use Modules\Platform\App\Infrastructure\Tenancy\DefaultTenantContext;
use Modules\Platform\App\Infrastructure\Tenancy\RegistersTenantMacro;
use Modules\Platform\App\Infrastructure\Tenancy\SubdomainTenantResolver;

class PlatformServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind the concrete as the canonical singleton and alias the
        // public interface to it, so consumers type-hinting either class
        // share ONE instance (middleware and route closures must agree).
        $this->app->singleton(DefaultTenantContext::class);
        $this->app->alias(DefaultTenantContext::class, TenantContext::class);

        $this->app->singleton(SubdomainTenantResolver::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerMigrations();
        $this->registerMiddleware();
        $this->registerSchemaMacro();
    }

    /**
     * Register the Blueprint::tenantId() macro.
     */
    protected function registerSchemaMacro(): void
    {
        RegistersTenantMacro::register();
    }

    /**
     * Publish/register the tenancy config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../../../config/tenancy.php' => config_path('tenancy.php'),
        ], 'platform-config');
    }

    /**
     * Register the module's migration files.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    }

    /**
     * Register the tenant middleware alias ('tenant') for routes/groups
     * that need explicit resolution (web group prepends the middleware
     * globally via bootstrap/app.php).
     */
    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('tenant', ResolveTenant::class);
    }
}
