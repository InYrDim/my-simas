<?php

namespace Modules\Core\App\Contracts;

/**
 * Registry of the reports listed on Statistik & Laporan. Each module
 * registers its own reports from its own service provider — Core never
 * imports a feature module.
 *
 * A report is offered only while its module is active for the current
 * tenant and the signed-in user holds the permission of its definition.
 */
interface ReportRegistry
{
    /**
     * Register a report for the given module key. Called from the owning
     * module's service provider during boot. Idempotent per class.
     *
     * @param  class-string<Report>  $report
     */
    public function register(string $module, string $report): void;
}
