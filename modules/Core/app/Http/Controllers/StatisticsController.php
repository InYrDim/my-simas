<?php

namespace Modules\Core\App\Http\Controllers;

use Inertia\Response;
use Modules\Core\App\Domain\Actions\ResolveReportPeriod;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Infrastructure\Insight\DefaultStatisticsRegistry;

/**
 * Statistik: the school in numbers. The figures and panels come from the
 * statistics registry — Core's own and those of every module active for
 * the school — for the active academic year.
 */
final class StatisticsController
{
    use RendersMasterPage;

    public function __invoke(ResolveReportPeriod $resolvePeriod, DefaultStatisticsRegistry $statistics): Response
    {
        $period = $resolvePeriod->handle();

        return $this->renderMaster('Core/Insight/Statistics', [
            'period' => $period?->name,
            'figures' => $statistics->figures($period),
            'panels' => $statistics->panels($period),
        ]);
    }
}
