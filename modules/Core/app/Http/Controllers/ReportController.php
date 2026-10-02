<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Response;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\Report;
use Modules\Core\App\Domain\Actions\ResolveReportPeriod;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Infrastructure\Csv\CsvWriter;
use Modules\Core\App\Infrastructure\Insight\DefaultReportRegistry;
use Modules\Platform\App\Contracts\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan: the catalogue of reports a school can take out, each for one
 * academic year, as a CSV file or as a print view. A report is made the
 * moment it is asked for — nothing is stored.
 *
 * The reports come from the report registry (Core's own and those of
 * every module active for the school); this controller only checks the
 * permission and turns the table into a file or a page.
 */
final class ReportController
{
    use RendersMasterPage;

    public function __construct(
        private readonly DefaultReportRegistry $reports,
        private readonly ResolveReportPeriod $resolvePeriod,
    ) {}

    public function index(Request $request): Response
    {
        $period = $this->period($request);

        return $this->renderMaster('Core/Insight/Reports', [
            'groups' => $this->reports->catalogue(),
            'years' => AcademicYear::query()->orderByDesc('start_date')->get()
                ->map(fn (AcademicYear $year): array => [
                    'value' => (string) $year->id,
                    'label' => $year->isActive() ? "{$year->name} (aktif)" : $year->name,
                ])
                ->all(),
            'yearId' => $period === null ? '' : (string) $period->academicYearId,
        ]);
    }

    public function download(Request $request, string $report, CsvWriter $writer): StreamedResponse
    {
        [$report, $period] = $this->requested($request, $report);

        $table = $report->table($period);
        $fileName = $report->definition()->key.'-'.trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $period->name), '-').'.csv';

        return response()->streamDownload(function () use ($writer, $table): void {
            $writer->output($table->columns, $table->rows);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The report as a page made for the browser's print dialog ("Simpan
     * sebagai PDF" included).
     */
    public function printView(Request $request, string $report, TenantContext $context): Response
    {
        [$report, $period] = $this->requested($request, $report);

        $table = $report->table($period);

        return $this->renderMaster('Core/Insight/ReportPrint', [
            'title' => $table->title,
            'period' => $period->name,
            'printedOn' => now($context->currentOrFail()->timezone)->settings(['locale' => 'id'])->isoFormat('D MMMM Y'),
            'columns' => $table->columns,
            'rows' => $table->rows,
        ]);
    }

    /**
     * The report and academic year a request asks for: 404 for a report
     * that does not exist here (unknown, only announced, or of a module
     * the school has not enabled) and for a school without an academic
     * year, 403 for one the user may not take out.
     *
     * @return array{Report, ReportPeriod}
     */
    private function requested(Request $request, string $key): array
    {
        $report = $this->reports->find($key);

        abort_if($report === null, 404);

        $permission = $report->definition()->permission;

        abort_unless($permission === null || Gate::allows($permission), 403);

        $period = $this->period($request);

        abort_if($period === null, 404);

        return [$report, $period];
    }

    private function period(Request $request): ?ReportPeriod
    {
        return $this->resolvePeriod->handle($request->integer('tahun') ?: null);
    }
}
