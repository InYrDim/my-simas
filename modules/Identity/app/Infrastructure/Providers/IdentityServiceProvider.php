<?php

namespace Modules\Identity\App\Infrastructure\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Identity\App\Domain\Actions\DefaultUserResolver;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\App\Domain\Policies\UserPolicy;
use Modules\Identity\App\Infrastructure\Accounts\DefaultAccountProvisioner;
use Modules\Identity\App\Infrastructure\Auth\DefaultSchoolSessionOpener;
use Modules\Identity\App\Infrastructure\Auth\TenantPasswordResetServiceProvider;
use Modules\Identity\App\Infrastructure\Commands\RolesSyncCommand;
use Modules\Identity\App\Infrastructure\Onboarding\ProvisionFirstAdmin;
use Modules\Identity\App\Infrastructure\Permissions\SeedDefaultRoles;
use Modules\Platform\App\Contracts\Events\TenantApproved;
use Modules\Platform\App\Contracts\Events\TenantCreated;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\SchoolSessionOpener;
use Modules\Platform\App\Contracts\TenantNavigation;

class IdentityServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ResolvesUsers::class, DefaultUserResolver::class);
        $this->app->singleton(AccountProvisioner::class, DefaultAccountProvisioner::class);

        // Platform defines SchoolSessionOpener (its applicant login needs
        // it) but only Identity knows school users: replace Platform's
        // refusing default.
        $this->app->singleton(SchoolSessionOpener::class, DefaultSchoolSessionOpener::class);

        // Default role set (machine names, labels, permission sets).
        $this->mergeConfigFrom(__DIR__.'/../../../config/roles.php', 'roles');
        $this->mergeConfigFrom(__DIR__.'/../../../config/permission_labels.php', 'permission_labels');

        // NOTE: the tenant-scoped password broker swap
        // (TenantPasswordResetServiceProvider) is NOT registered here.
        // Laravel's PasswordResetServiceProvider is DEFERRED: its
        // bindings overwrite eager ones at first resolution. The swap
        // lives in bootstrap/providers.php so the deferred services map
        // (service => provider) points at OUR provider — Laravel's
        // never loads for auth.password.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerModuleKey();
        $this->registerMailViews();
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerPolicies();
        $this->registerIdentityPermissions();
        $this->registerNavigation();
        $this->listenForTenantCreated();
        $this->listenForTenantApproved();
        $this->commands([RolesSyncCommand::class]);
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
     * Register this module's school-side sidebar entries.
     */
    protected function registerNavigation(): void
    {
        $this->app->make(TenantNavigation::class)->register('identity', [
            [
                'label' => 'Ganti Kata Sandi',
                'icon' => 'key-round',
                'route' => 'password.change',
                'group' => 'Saya',
                'order' => 19,
            ],
            [
                'label' => 'Pengguna',
                'icon' => 'users',
                'route' => 'identity.users.index',
                'permission' => 'identity.users.view',
                'group' => 'Administrasi',
                'order' => 70,
            ],
            [
                'label' => 'Sistem',
                'icon' => 'shield-check',
                'route' => 'identity.system.roles',
                'permission' => 'identity.users.view',
                'group' => 'Administrasi',
                'order' => 90,
                'children' => [
                    ['label' => 'Peran', 'route' => 'identity.system.roles', 'permission' => 'identity.users.view'],
                    ['label' => 'Izin', 'route' => 'identity.system.permissions', 'permission' => 'identity.users.view'],
                ],
            ],
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

    /**
     * Provision the first school admin the moment an application is
     * approved (Fase 2 Stage 8) — see ProvisionFirstAdmin.
     */
    protected function listenForTenantApproved(): void
    {
        Event::listen(TenantApproved::class, ProvisionFirstAdmin::class);
    }

    /**
     * Register the module's mail templates under the `Identity::`
     * namespace (modules/Identity/mail). A dot-path like
     * "modules/Identity/mail/…" does NOT work: the view finder maps
     * dots to directory separators, so it would search
     * modules/Identity/mail/modules/Identity/mail/….blade.php.
     */
    protected function registerMailViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../../mail', 'Identity');
    }
}
