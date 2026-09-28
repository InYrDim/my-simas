<?php

namespace Modules\Core\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Platform\App\Contracts\ModuleRegistry;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerModuleKey();
        $this->loadRoutesFrom(__DIR__.'/../../../routes/web.php');
        // API routes bring their own middleware grouping inside the file.
        $this->loadRoutesFrom(__DIR__.'/../../../routes/api.php');
    }

    /**
     * Register the module key with Platform's module registry. core is
     * declared always-active by its OWN module (not Platform hardcode).
     */
    protected function registerModuleKey(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        $registry->register('core', ['label' => 'Core']);
        $registry->markAlwaysActive('core');
    }
}
