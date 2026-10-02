<?php

namespace Modules\Ppdb\App\Infrastructure\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Core\App\Contracts\NoticeRegistry;
use Modules\Core\App\Contracts\ReportRegistry;
use Modules\Core\App\Contracts\StatisticsRegistry;
use Modules\Platform\App\Contracts\ModuleRegistry;
use Modules\Platform\App\Contracts\PermissionRegistry;
use Modules\Platform\App\Contracts\TenantNavigation;
use Modules\Ppdb\App\Domain\Notifications\ResultNotices;
use Modules\Ppdb\App\Domain\Reports\ApplicantListReport;
use Modules\Ppdb\App\Domain\Reports\SelectionResultReport;
use Modules\Ppdb\App\Domain\Statistics\AdmissionStatistics;
use Modules\Ppdb\App\Domain\Support\DocumentCheck;
use Modules\Ppdb\App\Domain\Support\ResultAnnouncer;
use Modules\Ppdb\App\Infrastructure\Notifications\WhatsappResultAnnouncer;
use Modules\Ppdb\App\Infrastructure\Stubs\AlwaysCompleteDocumentCheck;

class PpdbServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../../config/permission_labels.php', 'permission_labels');

        // Stand-in until applicants can upload documents: see the class.
        $this->app->bind(DocumentCheck::class, AlwaysCompleteDocumentCheck::class);

        $this->app->bind(ResultAnnouncer::class, WhatsappResultAnnouncer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerModuleKey();
        $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
        // A dot-path into modules/ does not work: mail views resolve
        // through the `Ppdb::` namespace.
        $this->loadViewsFrom(__DIR__.'/../../../mail', 'Ppdb');
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
        $this->app->make(ModuleRegistry::class)->register('ppdb', ['label' => 'PPDB']);
    }

    /**
     * Register the permission names this module owns. Attached to the
     * default roles through Identity's config/roles.php (names only).
     */
    protected function registerPermissions(): void
    {
        $this->app->make(PermissionRegistry::class)->register('ppdb', [
            'ppdb.view',
            'ppdb.applicants.manage',
            'ppdb.selection.manage',
            'ppdb.settings.manage',
        ]);
    }

    /**
     * Register the WhatsApp notice PPDB can send to guardians. The school
     * switches it on and words it on Integrasi › WhatsApp.
     */
    protected function registerNotices(): void
    {
        $registry = $this->app->make(NoticeRegistry::class);

        foreach (ResultNotices::kinds() as $kind) {
            $registry->register('ppdb', $kind);
        }
    }

    /**
     * Register the admissions reports and figures for Statistik & Laporan;
     * their keys take the place of the entries Core announced.
     */
    protected function registerInsight(): void
    {
        $reports = $this->app->make(ReportRegistry::class);

        $reports->register('ppdb', ApplicantListReport::class);
        $reports->register('ppdb', SelectionResultReport::class);

        $this->app->make(StatisticsRegistry::class)->register('ppdb', AdmissionStatistics::class);
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
                'permission' => 'ppdb.view',
                'group' => 'Operasional',
                'order' => 52,
                'children' => [
                    ['label' => 'Ringkasan', 'route' => 'ppdb.overview', 'match' => 'exact'],
                    ['label' => 'Pendaftar', 'route' => 'ppdb.applicants'],
                    ['label' => 'Seleksi & Pengumuman', 'route' => 'ppdb.selection'],
                    ['label' => 'Formulir', 'route' => 'ppdb.form', 'permission' => 'ppdb.settings.manage'],
                    ['label' => 'Pengaturan', 'route' => 'ppdb.settings', 'permission' => 'ppdb.settings.manage'],
                ],
            ],
        ]);
    }
}
