<?php

namespace Modules\Attendance\App\Infrastructure\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Attendance\App\Domain\Dashboard\AttendanceDashboard;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Attendance\App\Domain\Notifications\AttendanceNotices;
use Modules\Attendance\App\Domain\Reports\ClassAttendanceReport;
use Modules\Attendance\App\Domain\Reports\MonthlyAttendanceReport;
use Modules\Attendance\App\Domain\Statistics\AttendanceStatistics;
use Modules\Attendance\App\Domain\Students\StaticQrStudentActions;
use Modules\Core\App\Contracts\DashboardRegistry;
use Modules\Core\App\Contracts\NoticeRegistry;
use Modules\Core\App\Contracts\ReportRegistry;
use Modules\Core\App\Contracts\StatisticsRegistry;
use Modules\Core\App\Contracts\StudentActionRegistry;
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
            'attendance.class.view-own',
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
        Gate::define('attendance.class.lesson.use', fn (Authenticatable $user): bool => Gate::forUser($user)->allows('attendance.class.record') && AttendanceSetting::lessonEnabled());
        Gate::define('attendance.lesson.school', fn (Authenticatable $user): bool => Gate::forUser($user)->allows('attendance.lesson.record') && AttendanceSetting::lessonEnabled());
        Gate::define('attendance.scan.use', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['attendance.gate.use', 'attendance.lesson.use']));
        Gate::define('attendance.scan.school', fn (Authenticatable $user): bool => Gate::forUser($user)->any(['attendance.gate.use', 'attendance.lesson.school']));
        Gate::define('attendance.static-qr.print', fn (Authenticatable $user): bool => Gate::forUser($user)->allows('attendance.settings.manage') && AttendanceSetting::staticQrEnabled());
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
        $this->app->make(DashboardRegistry::class)->register('attendance', AttendanceDashboard::class);
        $this->app->make(StudentActionRegistry::class)->register('attendance', StaticQrStudentActions::class);
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
                    ['label' => 'Absensi Gerbang', 'route' => 'attendance.input', 'permission' => 'attendance.daily.record', 'shortcut' => true],
                    ['label' => 'Absensi Pelajaran', 'route' => 'attendance.lessons', 'permission' => 'attendance.lesson.school'],
                    ['label' => 'Pindai QR', 'route' => 'attendance.scan', 'permission' => 'attendance.scan.school', 'shortcut' => true],
                    ['label' => 'Rekap Bulanan', 'route' => 'attendance.monthly', 'permission' => 'attendance.view'],
                    ['label' => 'Pengaturan', 'route' => 'attendance.settings', 'permission' => 'attendance.settings.manage'],
                ],
            ],
            [
                'label' => 'Kelas Mengajar',
                'icon' => 'school',
                'route' => 'attendance.my-classes',
                'permission' => 'attendance.class.record',
                'group' => 'Saya',
                'order' => 14,
                'children' => [
                    ['label' => 'Kelas Aktif', 'route' => 'attendance.my-classes'],
                    ['label' => 'Absensi Kelas', 'route' => 'attendance.class-roll', 'permission' => 'attendance.class.lesson.use'],
                    ['label' => 'Pindai QR', 'route' => 'attendance.scan', 'permission' => 'attendance.scan.use', 'shortcut' => true],
                ],
            ],
            [
                'label' => 'Jadwal Saya',
                'icon' => 'calendar-clock',
                'route' => 'attendance.schedule',
                'permission' => 'attendance.class.record',
                'group' => 'Saya',
                'order' => 13,
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
                'label' => 'Kelas Saya',
                'icon' => 'school',
                'route' => 'attendance.class-mine',
                'permission' => 'attendance.class.view-own',
                'group' => 'Saya',
                'order' => 14,
                'children' => [
                    ['label' => 'Info Kelas', 'route' => 'attendance.class-mine', 'match' => 'exact'],
                    ['label' => 'Jadwal Pelajaran', 'route' => 'attendance.class-mine.timetable'],
                    ['label' => 'Mata Pelajaran & Guru', 'route' => 'attendance.class-mine.subjects'],
                    ['label' => 'Absensi Saya', 'route' => 'attendance.mine', 'permission' => 'attendance.mine.view'],
                ],
            ],
        ]);
    }
}
