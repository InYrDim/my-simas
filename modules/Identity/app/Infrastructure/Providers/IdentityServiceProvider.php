<?php

namespace Modules\Identity\App\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Identity\App\Domain\Actions\DefaultUserResolver;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Domain\Policies\UserPolicy;
use Modules\Identity\App\Infrastructure\Permissions\SeedDefaultRoles;
use Modules\Platform\App\Contracts\Events\TenantCreated;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ResolvesUsers::class, DefaultUserResolver::class);

        // Default role set (machine names, labels, permission sets).
        $this->mergeConfigFrom(__DIR__.'/../../../config/roles.php', 'roles');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerModuleKey();
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerPolicies();
        $this->registerIdentityPermissions();
        $this->listenForTenantCreated();
    }

    /**
     * Register the module key with Platform's module registry.
     * identity is flag-controlled (not always-active): tenant dapat
     * dinonaktifkan modul identitasnya.
     */
    protected function registerModuleKey(): void
    {
        $this->app->make(ModuleRegistry::class)->register('identity', ['label' => 'Identity']);
    }

    /**
     * Register the module's migration files.
     */
    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
    }

    /**
     * Register the module's route files.
     */
    protected function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../../routes/web.php');
        // API routes bring their own middleware grouping inside the file.
        $this->loadRoutesFrom(__DIR__.'/../../../routes/api.php');
    }

    /**
     * Register the module's policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Register the permission names this module owns. Materialised
     * into rows by permissions:sync and by TenantRoles::ensure() when
     * default roles are seeded (Fase 2 Stage 2).
     */
    protected function registerIdentityPermissions(): void
    {
        $this->app->make(PermissionRegistry::class)->register('identity', [
            'identity.users.view',
            'identity.users.create',
            'identity.users.update',
            'identity.users.deactivate',
            'identity.users.sendReset',
        ]);
    }

    /**
     * Seed each new tenant's default school roles the moment the
     * tenant row exists (see config/roles.php for the role set).
     */
    protected function listenForTenantCreated(): void
    {
        Event::listen(TenantCreated::class, SeedDefaultRoles::class);
    }
}
