<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;

/**
 * A report a school can download from Statistik & Laporan. The owning
 * module implements it and registers the class with ReportRegistry; Core
 * lists it, checks the permission and renders the table as CSV or as a
 * print view.
 *
 * Implementations are resolved from the container and run inside the
 * tenant context of the request, so they read only the current school.
 */
interface Report
{
    public function definition(): ReportDefinition;

    public function table(ReportPeriod $period): ReportTable;
}
