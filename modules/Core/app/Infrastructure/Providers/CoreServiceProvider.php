<?php

namespace Modules\Core\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\TenantNavigation;

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
                'order' => 20,
                'children' => [
                    ['label' => 'Profil Sekolah', 'route' => 'core.master.school'],
                    ['label' => 'Tahun Ajaran', 'route' => 'core.master.years'],
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
                'order' => 40,
            ],
        ]);
    }
}
