<?php

namespace Modules\Attendance\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantNavigation;

class AttendanceServiceProvider extends ServiceProvider
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
        $this->app->make(ModuleRegistry::class)->register('attendance', ['label' => 'Absensi']);
    }

    /**
     * Register this module's school-side sidebar entries.
     */
    protected function registerNavigation(): void
    {
        $this->app->make(TenantNavigation::class)->register('attendance', [
            [
                'label' => 'Absensi',
                'icon' => 'clipboard-check',
                'route' => 'attendance.overview',
                'order' => 35,
                'children' => [
                    ['label' => 'Rekap Hari Ini', 'route' => 'attendance.overview', 'match' => 'exact'],
                    ['label' => 'Input Absensi', 'route' => 'attendance.input'],
                    ['label' => 'Rekap Bulanan', 'route' => 'attendance.monthly'],
                ],
            ],
        ]);
    }
}
