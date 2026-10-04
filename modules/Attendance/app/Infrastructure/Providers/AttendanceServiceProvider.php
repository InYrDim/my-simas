<?php

namespace Modules\Attendance\App\Infrastructure\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
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
        $this->registerFeatureAbilities();
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
            'attendance.mine.view',
            'attendance.class.record',
        ]);
    }

    /**
     * Abilities that join a permission with the school's switch for the
     * gate or the lesson attendance; routes and sidebar entries ask these
     * instead of the bare permission.
     */
    protected function registerFeatureAbilities(): void
    {
        Gate::define('attendance.daily.use', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['attendance.daily.record', 'attendance.class.record']));
        Gate::define('attendance.gate.use', fn (Authenticatable $user): bool => Gate::forUser($user)->allows('attendance.daily.record') && AttendanceSetting::gateEnabled());
        Gate::define('attendance.lesson.use', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['attendance.lesson.record', 'attendance.class.record']) && AttendanceSetting::lessonEnabled());
        Gate::define('attendance.lesson.school', fn (Authenticatable $user): bool => Gate::forUser($user)->allows('attendance.lesson.record') && AttendanceSetting::lessonEnabled());
        Gate::define('attendance.scan.use', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['attendance.gate.use', 'attendance.lesson.use']));
        Gate::define('attendance.scan.school', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['attendance.gate.use', 'attendance.lesson.school']));
        Gate::define('attendance.qr.use', fn (Authenticatable $user): bool => Gate::forUser($user)->allows('attendance.qr.show') && (AttendanceSetting::gateEnabled() || AttendanceSetting::lessonEnabled()));
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
                    ['label' => 'Input Absensi', 'route' => 'attendance.input', 'permission' => 'attendance.daily.record', 'shortcut' => true],
                    ['label' => 'Jam Pelajaran', 'route' => 'attendance.lessons', 'permission' => 'attendance.lesson.school'],
                    ['label' => 'Pindai QR', 'route' => 'attendance.scan', 'permission' => 'attendance.scan.school', 'shortcut' => true],
                    ['label' => 'Rekap Bulanan', 'route' => 'attendance.monthly', 'permission' => 'attendance.view'],
                    ['label' => 'Pengaturan', 'route' => 'attendance.settings', 'permission' => 'attendance.settings.manage'],
                ],
            ],
            [
                'label' => 'Absensi Saya',
                'icon' => 'clipboard-check',
                'route' => 'attendance.input',
                'permission' => 'attendance.class.record',
                'group' => 'Saya',
                'order' => 13,
                'children' => [
                    ['label' => 'Input Absensi', 'route' => 'attendance.input', 'permission' => 'attendance.daily.use', 'shortcut' => true],
                    ['label' => 'Jam Pelajaran', 'route' => 'attendance.lessons', 'permission' => 'attendance.lesson.use'],
                    ['label' => 'Pindai QR', 'route' => 'attendance.scan', 'permission' => 'attendance.scan.use', 'shortcut' => true],
                ],
            ],
            [
                'label' => 'QR Absensi',
                'icon' => 'qr-code',
                'route' => 'attendance.my-qr',
                'permission' => 'attendance.qr.use',
                'shortcut' => true,
                'group' => 'Saya',
                'order' => 12,
            ],
            [
                'label' => 'Absensi Saya',
                'icon' => 'calendar-check',
                'route' => 'attendance.mine',
                'permission' => 'attendance.mine.view',
                'group' => 'Saya',
                'order' => 13,
            ],
        ]);
    }
}
