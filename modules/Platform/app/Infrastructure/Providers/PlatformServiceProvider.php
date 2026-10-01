<?php

namespace Modules\Platform\App\Infrastructure\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\TenantApplications;
use Modules\Platform\App\Contracts\TenantCache;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantDirectory;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Contracts\TenantNavigation;
use Modules\Platform\App\Contracts\TenantRoles;
use Modules\Platform\App\Contracts\TenantStorage;
use Modules\Platform\App\Contracts\TenantUrl;
use Modules\Platform\App\Http\Middleware\EnsureModuleActive;
use Modules\Platform\App\Http\Middleware\ResolveTenant;
use Modules\Platform\App\Infrastructure\Billing\AlwaysSucceedsPaymentGateway;
use Modules\Platform\App\Infrastructure\Billing\BillingSummary;
use Modules\Platform\App\Infrastructure\Billing\InvoiceIssuer;
use Modules\Platform\App\Infrastructure\Billing\PaymentGateway;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Modules\Platform\App\Infrastructure\Commands\PermissionsSyncCommand;
use Modules\Platform\App\Infrastructure\Commands\ProviderCreateUserCommand;
use Modules\Platform\App\Infrastructure\Commands\TenantActivateCommand;
use Modules\Platform\App\Infrastructure\Commands\TenantCreateCommand;
use Modules\Platform\App\Infrastructure\Commands\TenantListCommand;
use Modules\Platform\App\Infrastructure\Commands\TenantModulesCommand;
use Modules\Platform\App\Infrastructure\Commands\TenantRunCommand;
use Modules\Platform\App\Infrastructure\Commands\TenantSuspendCommand;
use Modules\Platform\App\Infrastructure\Modules\DefaultModuleRegistry;
use Modules\Platform\App\Infrastructure\Modules\DefaultTenantModules;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\App\Infrastructure\Modules\TenantModulesCache;
use Modules\Platform\App\Infrastructure\Navigation\DefaultTenantNavigation;
use Modules\Platform\App\Infrastructure\Onboarding\DefaultTenantApplications;
use Modules\Platform\App\Infrastructure\Permissions\DefaultPermissionRegistry;
use Modules\Platform\App\Infrastructure\Permissions\DefaultTenantRoles;
use Modules\Platform\App\Infrastructure\Permissions\PermissionSync;
use Modules\Platform\App\Infrastructure\Permissions\TenantPermissionBridge;
use Modules\Platform\App\Infrastructure\Permissions\TenantRoleResolver;
use Modules\Platform\App\Infrastructure\Tenancy\DefaultTenantContext;
use Modules\Platform\App\Infrastructure\Tenancy\DefaultTenantDirectory;
use Modules\Platform\App\Infrastructure\Tenancy\DefaultTenantUrl;
use Modules\Platform\App\Infrastructure\Tenancy\PartitionedTenantCache;
use Modules\Platform\App\Infrastructure\Tenancy\PartitionedTenantStorage;
use Modules\Platform\App\Infrastructure\Tenancy\RegistersTenantMacro;
use Modules\Platform\App\Infrastructure\Tenancy\SchoolCodeTenantResolver;
use Modules\Platform\App\Infrastructure\Tenancy\TenantLifecycle;
use Modules\Platform\App\Infrastructure\Tenancy\TenantQueueContext;

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

        $this->app->singleton(SchoolCodeTenantResolver::class);

        // Tenant-partitioned cache/storage: interface-aliased singletons
        // so interface and concrete type-hints share one instance.
        $this->app->singleton(PartitionedTenantCache::class);
        $this->app->alias(PartitionedTenantCache::class, TenantCache::class);

        $this->app->singleton(PartitionedTenantStorage::class);
        $this->app->alias(PartitionedTenantStorage::class, TenantStorage::class);

        $this->app->singleton(TenantQueueContext::class);

        // Read-only tenant lookup for other modules' provider-console pages
        // and the shared status/profile write path.
        $this->app->singleton(DefaultTenantDirectory::class);
        $this->app->alias(DefaultTenantDirectory::class, TenantDirectory::class);
        $this->app->singleton(TenantLifecycle::class);

        // Tenant URL resolution for queue-built links (Fase 2 Stage 4):
        // interface-aliased singleton, same pattern as the tenancy
        // bindings.
        $this->app->singleton(DefaultTenantUrl::class);
        $this->app->alias(DefaultTenantUrl::class, TenantUrl::class);

        // Module registry + per-tenant flags: interface-aliased
        // singletons, same pattern as the tenancy bindings.
        $this->app->singleton(DefaultModuleRegistry::class);
        $this->app->alias(DefaultModuleRegistry::class, ModuleRegistry::class);

        $this->app->singleton(TenantModulesCache::class);
        $this->app->singleton(DefaultTenantModules::class);
        $this->app->alias(DefaultTenantModules::class, TenantModules::class);

        $this->app->singleton(ModuleFlagManager::class);

        // Permission layer (Stage 6): registry + sync + the Spatie seam.
        $this->app->singleton(DefaultPermissionRegistry::class);
        $this->app->alias(DefaultPermissionRegistry::class, PermissionRegistry::class);

        // School-side sidebar registry: each module registers its own
        // entries; the tenant shell reads the filtered list.
        $this->app->singleton(DefaultTenantNavigation::class);
        $this->app->alias(DefaultTenantNavigation::class, TenantNavigation::class);

        $this->app->singleton(TenantRoleResolver::class);
        $this->app->singleton(PermissionSync::class);
        $this->app->singleton(TenantPermissionBridge::class);

        // Explicit-tenant-id role materialisation (Fase 2 Stage 2):
        // interface-aliased singleton, same pattern as the tenancy
        // bindings.
        $this->app->singleton(DefaultTenantRoles::class);
        $this->app->alias(DefaultTenantRoles::class, TenantRoles::class);

        // School onboarding pipeline (Fase 2 Stage 5): interface-aliased
        // singleton, same pattern as the tenancy bindings.
        $this->app->singleton(DefaultTenantApplications::class);
        $this->app->alias(DefaultTenantApplications::class, TenantApplications::class);

        // Subscription billing (provider side). The gateway is a stub that
        // always succeeds until a real payment provider is chosen.
        $this->app->singleton(PaymentGateway::class, AlwaysSucceedsPaymentGateway::class);
        $this->app->singleton(InvoiceIssuer::class);
        $this->app->singleton(SubscriptionManager::class);
        $this->app->singleton(BillingSummary::class);

        $this->registerCommands();
    }

    /**
     * Register the module's Artisan commands (Stage 7).
     */
    protected function registerCommands(): void
    {
        $this->commands([
            TenantCreateCommand::class,
            TenantListCommand::class,
            TenantActivateCommand::class,
            TenantSuspendCommand::class,
            TenantModulesCommand::class,
            TenantRunCommand::class,
            PermissionsSyncCommand::class,
            ProviderCreateUserCommand::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->registerMigrations();
        $this->registerMailViews();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerSchemaMacro();
        $this->registerQueueContext();
        $this->registerInertiaPagePaths();
    }

    /**
     * Stamp queued payloads with the dispatching tenant and restore that
     * context when the job runs (save/restore, sync-driver safe).
     */
    protected function registerQueueContext(): void
    {
        $this->app->make(TenantQueueContext::class)->register();
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
     * Register the module's mail templates under the `Platform::`
     * namespace (modules/Platform/mail) — a dot-path into modules/ does
     * not resolve through the view finder.
     */
    protected function registerMailViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../../mail', 'Platform');
    }

    /**
     * Register the module's route files (provider console).
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../../routes/web.php');
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

        $router->aliasMiddleware('module', EnsureModuleActive::class);

    }

    /**
     * Make module page components resolvable server-side. Inertia's
     * view finder only knows `inertia.pages.paths` (root js/pages by
     * default), so ensure_pages_exist / assertInertia would fail for
     * module pages like "Platform/Applications/Index". Each module's
     * Pages dir is added as a view path and components resolve as
     * "<Module>/<Page>" — the same names app.tsx's resolver uses.
     */
    protected function registerInertiaPagePaths(): void
    {
        $paths = (array) config('inertia.pages.paths', []);

        foreach (glob(base_path('modules/*'), GLOB_ONLYDIR) ?: [] as $moduleDir) {
            $pagesDir = $moduleDir.'/resources/js/Pages';

            if (is_dir($pagesDir)) {
                $paths[] = $pagesDir;
            }
        }

        config(['inertia.pages.paths' => $paths]);
    }
}
