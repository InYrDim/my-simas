<?php

namespace Modules\Core\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\App\Contracts\BellSchedule;
use Modules\Core\App\Contracts\ClassDirectory;
use Modules\Core\App\Contracts\ContactNotifier;
use Modules\Core\App\Contracts\GuardianNotifier;
use Modules\Core\App\Contracts\NoticeRegistry;
use Modules\Core\App\Contracts\ReportRegistry;
use Modules\Core\App\Contracts\StatisticsRegistry;
use Modules\Core\App\Contracts\StudentAdmission;
use Modules\Core\App\Contracts\StudentDirectory;
use Modules\Core\App\Contracts\TeacherSchedule;
use Modules\Core\App\Domain\Reports\StudentListReport;
use Modules\Core\App\Domain\Reports\StudentMutationReport;
use Modules\Core\App\Domain\Reports\TeachingLoadReport;
use Modules\Core\App\Domain\Statistics\SchoolStatistics;
use Modules\Core\App\Infrastructure\Admission\DefaultStudentAdmission;
use Modules\Core\App\Infrastructure\Directory\EloquentBellSchedule;
use Modules\Core\App\Infrastructure\Directory\EloquentClassDirectory;
use Modules\Core\App\Infrastructure\Directory\EloquentStudentDirectory;
use Modules\Core\App\Infrastructure\Directory\EloquentTeacherSchedule;
use Modules\Core\App\Infrastructure\Insight\DefaultReportRegistry;
use Modules\Core\App\Infrastructure\Insight\DefaultStatisticsRegistry;
use Modules\Core\App\Infrastructure\Whatsapp\DefaultContactNotifier;
use Modules\Core\App\Infrastructure\Whatsapp\DefaultGuardianNotifier;
use Modules\Core\App\Infrastructure\Whatsapp\DefaultNoticeRegistry;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\TenantNavigation;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/permission_labels.php', 'permission_labels');
        $this->mergeConfigFrom(__DIR__.'/../../../config/insight.php', 'insight');
        $this->mergeConfigFrom(__DIR__.'/../../../config/notices.php', 'notices');

        // Statistik & Laporan registries: interface-aliased singletons, so
        // the modules registering through the contract and Core's own
        // controllers reading the concrete share one instance.
        $this->app->singleton(DefaultReportRegistry::class);
        $this->app->alias(DefaultReportRegistry::class, ReportRegistry::class);

        $this->app->singleton(DefaultStatisticsRegistry::class);
        $this->app->alias(DefaultStatisticsRegistry::class, StatisticsRegistry::class);

        // WhatsApp notices to guardians: modules register their kinds and
        // send through the notifier; same aliasing as the registries above.
        $this->app->singleton(DefaultNoticeRegistry::class);
        $this->app->alias(DefaultNoticeRegistry::class, NoticeRegistry::class);

        $this->app->singleton(GuardianNotifier::class, DefaultGuardianNotifier::class);
        $this->app->singleton(ContactNotifier::class, DefaultContactNotifier::class);

        // Read access to students, classes and the bell schedule for the
        // feature modules, which never see Core's models.
        $this->app->bind(StudentDirectory::class, EloquentStudentDirectory::class);
        $this->app->bind(ClassDirectory::class, EloquentClassDirectory::class);
        $this->app->bind(BellSchedule::class, EloquentBellSchedule::class);
        $this->app->bind(TeacherSchedule::class, EloquentTeacherSchedule::class);

        // Admitting a new student on behalf of a feature module (PPDB).
        $this->app->bind(StudentAdmission::class, DefaultStudentAdmission::class);
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
        $this->registerInsight();
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

    /**
     * Register the permission names this module owns. Attached to the
     * default roles through Identity's config/roles.php (names only).
     */
    protected function registerPermissions(): void
    {
        $this->app->make(PermissionRegistry::class)->register('core', [
            'core.master.view',
            'core.master.manage',
            'core.academic.view',
            'core.academic.manage',
            'core.integration.manage',
            'core.me.view',
            'core.teaching.view',
        ]);
    }

    /**
     * Register Core's own reports and figures for Statistik & Laporan,
     * through the same contracts the feature modules use.
     */
    protected function registerInsight(): void
    {
        $reports = $this->app->make(ReportRegistry::class);

        $reports->register('core', StudentListReport::class);
        $reports->register('core', StudentMutationReport::class);
        $reports->register('core', TeachingLoadReport::class);

        $this->app->make(StatisticsRegistry::class)->register('core', SchoolStatistics::class);
    }

    /**
     * Register this module's school-side sidebar entries.
     */
    protected function registerNavigation(): void
    {
        $this->app->make(TenantNavigation::class)->register('core', [
            [
                'label' => 'Beranda',
                'icon' => 'layout-dashboard',
                'route' => 'home',
                'order' => 10,
            ],
            [
                'label' => 'Profil Saya',
                'icon' => 'user-round',
                'route' => 'core.me.profile',
                'permission' => 'core.me.view',
                'group' => 'Saya',
                'order' => 11,
            ],
            [
                'label' => 'Jadwal Mengajar',
                'icon' => 'calendar-clock',
                'route' => 'core.me.timetable',
                'permission' => 'core.teaching.view',
                'group' => 'Saya',
                'order' => 15,
            ],
            [
                'label' => 'Master Data',
                'icon' => 'database',
                'route' => 'core.master.school',
                'permission' => 'core.master.view',
                'group' => 'Data Induk',
                'order' => 20,
                'children' => [
                    ['label' => 'Profil Sekolah', 'route' => 'core.master.school', 'permission' => 'core.master.manage'],
                    ['label' => 'Tahun Ajaran', 'route' => 'core.master.years'],
                    ['label' => 'Semester', 'route' => 'core.master.semesters'],
                    ['label' => 'Tingkat & Jurusan', 'route' => 'core.master.grades'],
                    ['label' => 'Kelas', 'route' => 'core.master.classes'],
                    ['label' => 'Mata Pelajaran', 'route' => 'core.master.subjects'],
                    ['label' => 'Ruangan', 'route' => 'core.master.rooms'],
                    ['label' => 'Ekstrakurikuler', 'route' => 'core.master.extracurriculars'],
                ],
            ],
            [
                'label' => 'Warga Sekolah',
                'icon' => 'users-round',
                'route' => 'core.master.students',
                'permission' => 'core.master.view',
                'group' => 'Data Induk',
                'order' => 25,
                'children' => [
                    ['label' => 'Siswa', 'route' => 'core.master.students'],
                    ['label' => 'Guru & Tendik', 'route' => 'core.master.teachers'],
                ],
            ],
            [
                'label' => 'Akademik',
                'icon' => 'graduation-cap',
                'route' => 'core.academic.placement',
                'permission' => 'core.academic.view',
                'group' => 'Data Induk',
                'order' => 30,
                'children' => [
                    ['label' => 'Penempatan Siswa', 'route' => 'core.academic.placement', 'permission' => 'core.academic.manage'],
                    ['label' => 'Pengampu Mapel', 'route' => 'core.academic.assignments', 'permission' => 'core.academic.manage'],
                    ['label' => 'Wali Kelas', 'route' => 'core.academic.homerooms', 'permission' => 'core.academic.manage'],
                    ['label' => 'Jam Pelajaran', 'route' => 'core.academic.periods'],
                    ['label' => 'Jadwal Pelajaran', 'route' => 'core.academic.timetable'],
                    ['label' => 'Kalender Akademik', 'route' => 'core.academic.calendar'],
                ],
            ],
            [
                'label' => 'Impor Data',
                'icon' => 'upload',
                'route' => 'core.manage.import',
                'permission' => 'core.master.manage',
                'group' => 'Data Induk',
                'order' => 40,
            ],
            [
                'label' => 'Statistik & Laporan',
                'icon' => 'chart-column',
                'route' => 'core.insight.statistics',
                'permission' => 'core.master.view',
                'group' => 'Operasional',
                'order' => 55,
                'children' => [
                    ['label' => 'Statistik', 'route' => 'core.insight.statistics'],
                    ['label' => 'Laporan', 'route' => 'core.insight.reports'],
                ],
            ],
            [
                'label' => 'Integrasi',
                'icon' => 'plug',
                'route' => 'core.integration.whatsapp',
                'permission' => 'core.integration.manage',
                'group' => 'Administrasi',
                'order' => 80,
                'children' => [
                    ['label' => 'WhatsApp', 'route' => 'core.integration.whatsapp'],
                ],
            ],
        ]);
    }
}
