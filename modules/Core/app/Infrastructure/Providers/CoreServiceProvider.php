<?php

namespace Modules\Core\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
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
        ]);
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
                'label' => 'Master Data',
                'icon' => 'database',
                'route' => 'core.master.school',
                'permission' => 'core.master.view',
                'group' => 'Data Induk',
                'order' => 20,
                'children' => [
                    ['label' => 'Profil Sekolah', 'route' => 'core.master.school'],
                    ['label' => 'Tahun Ajaran', 'route' => 'core.master.years'],
                    ['label' => 'Semester', 'route' => 'core.master.semesters'],
                    ['label' => 'Tingkat & Jurusan', 'route' => 'core.master.grades'],
                    ['label' => 'Kelas', 'route' => 'core.master.classes'],
                    ['label' => 'Mata Pelajaran', 'route' => 'core.master.subjects'],
                    ['label' => 'Guru & Tendik', 'route' => 'core.master.teachers'],
                    ['label' => 'Siswa', 'route' => 'core.master.students'],
                    ['label' => 'Ruangan', 'route' => 'core.master.rooms'],
                    ['label' => 'Ekstrakurikuler', 'route' => 'core.master.extracurriculars'],
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
                    ['label' => 'Penempatan Siswa', 'route' => 'core.academic.placement'],
                    ['label' => 'Pengampu Mapel', 'route' => 'core.academic.assignments'],
                    ['label' => 'Wali Kelas', 'route' => 'core.academic.homerooms'],
                    ['label' => 'Jam Pelajaran', 'route' => 'core.academic.periods'],
                    ['label' => 'Kalender Akademik', 'route' => 'core.academic.calendar'],
                ],
            ],
            [
                'label' => 'Impor Data',
                'icon' => 'upload',
                'route' => 'core.manage.import',
                'group' => 'Data Induk',
                'order' => 40,
            ],
            [
                'label' => 'Statistik & Laporan',
                'icon' => 'chart-column',
                'route' => 'core.insight.statistics',
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
                'group' => 'Administrasi',
                'order' => 80,
                'children' => [
                    ['label' => 'WhatsApp', 'route' => 'core.integration.whatsapp'],
                ],
            ],
        ]);
    }
}
