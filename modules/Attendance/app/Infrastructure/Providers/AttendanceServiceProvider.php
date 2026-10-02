<?php

namespace Modules\Attendance\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Attendance\App\Domain\Notifications\AttendanceNotices;
use Modules\Attendance\App\Domain\Reports\ClassAttendanceReport;
use Modules\Attendance\App\Domain\Reports\MonthlyAttendanceReport;
use Modules\Attendance\App\Domain\Statistics\AttendanceStatistics;
use Modules\Core\App\Contracts\NoticeRegistry;
use Modules\Core\App\Contracts\ReportRegistry;
use Modules\Core\App\Contracts\StatisticsRegistry;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\TenantNavigation;

class AttendanceServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/permission_labels.php', 'permission_labels');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerModuleKey();
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
        $this->registerPermissions();
        $this->registerNavigation();
        $this->registerNotices();
        $this->registerInsight();
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
     * Register the permission names this module owns. Attached to the
     * default roles through Identity's config/roles.php (names only).
     */
    protected function registerPermissions(): void
    {
        $this->app->make(PermissionRegistry::class)->register('attendance', [
            'attendance.view',
            'attendance.daily.record',
            'attendance.lesson.record',
            'attendance.settings.manage',
            'attendance.qr.show',
        ]);
    }

    /**
     * Register the WhatsApp notices attendance can send to guardians. The
     * school switches each one on and words it on Integrasi › WhatsApp.
     */
    protected function registerNotices(): void
    {
        $registry = $this->app->make(NoticeRegistry::class);

        foreach (AttendanceNotices::kinds() as $kind) {
            $registry->register('attendance', $kind);
        }
    }

    /**
     * Register the attendance reports and figures for Statistik & Laporan;
     * their keys take the place of the entries Core announced.
     */
    protected function registerInsight(): void
    {
        $reports = $this->app->make(ReportRegistry::class);

        $reports->register('attendance', MonthlyAttendanceReport::class);
        $reports->register('attendance', ClassAttendanceReport::class);

        $this->app->make(StatisticsRegistry::class)->register('attendance', AttendanceStatistics::class);
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
                'group' => 'Operasional',
                'order' => 50,
                'children' => [
                    ['label' => 'Rekap Hari Ini', 'route' => 'attendance.overview', 'permission' => 'attendance.view', 'match' => 'exact'],
                    ['label' => 'Input Absensi', 'route' => 'attendance.input', 'permission' => 'attendance.daily.record'],
                    ['label' => 'Jam Pelajaran', 'route' => 'attendance.lessons', 'permission' => 'attendance.lesson.record'],
                    ['label' => 'Pindai QR', 'route' => 'attendance.scan', 'permission' => 'attendance.daily.record'],
                    ['label' => 'Rekap Bulanan', 'route' => 'attendance.monthly', 'permission' => 'attendance.view'],
                    ['label' => 'Pengaturan', 'route' => 'attendance.settings', 'permission' => 'attendance.settings.manage'],
                ],
            ],
            [
                'label' => 'QR Absensi',
                'icon' => 'qr-code',
                'route' => 'attendance.my-qr',
                'permission' => 'attendance.qr.show',
                'group' => 'Operasional',
                'order' => 51,
            ],
        ]);
    }
}
