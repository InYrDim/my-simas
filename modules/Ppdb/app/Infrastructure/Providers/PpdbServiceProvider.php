<?php

namespace Modules\Ppdb\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantNavigation;

class PpdbServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerModuleKey();
        $this->registerNavigation();
        $this->loadRoutesFrom(__DIR__.'/../../../routes/web.php');
        // API routes bring their own middleware grouping inside the file.
        $this->loadRoutesFrom(__DIR__.'/../../../routes/api.php');
    }

    /**
     * Register the module key with Platform's module registry; the module
     * is enabled per tenant.
     */
    protected function registerModuleKey(): void
    {
        $this->app->make(ModuleRegistry::class)->register('ppdb', ['label' => 'PPDB']);
    }

    /**
     * Register this module's school-side sidebar entries.
     */
    protected function registerNavigation(): void
    {
        $this->app->make(TenantNavigation::class)->register('ppdb', [
            [
                'label' => 'PPDB',
                'icon' => 'user-plus',
                'route' => 'ppdb.overview',
                'order' => 36,
                'children' => [
                    ['label' => 'Ringkasan', 'route' => 'ppdb.overview', 'match' => 'exact'],
                    ['label' => 'Pendaftar', 'route' => 'ppdb.applicants'],
                    ['label' => 'Seleksi & Pengumuman', 'route' => 'ppdb.selection'],
                ],
            ],
        ]);
    }
}
