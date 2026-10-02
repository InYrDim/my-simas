<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\StatFigure;
use Modules\Core\App\Contracts\DTOs\StatPanel;

/**
 * A module's contribution to the Statistik page: headline numbers and
 * panels about its own data. Registered with StatisticsRegistry.
 *
 * Implementations are resolved from the container and run inside the
 * tenant context of the request. The period is null for a school that has
 * no academic year yet.
 */
interface StatisticsProvider
{
    /**
     * @return list<StatFigure>
     */
    public function figures(?ReportPeriod $period): array;

    /**
     * @return list<StatPanel>
     */
    public function panels(?ReportPeriod $period): array;
}
