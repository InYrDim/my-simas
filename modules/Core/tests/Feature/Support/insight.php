<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Testing\TestResponse;
use Modules\Core\App\Contracts\DTOs\ReportDefinition;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\ReportTable;
use Modules\Core\App\Contracts\DTOs\StatFigure;
use Modules\Core\App\Contracts\DTOs\StatPanel;
use Modules\Core\App\Contracts\Report;
use Modules\Core\App\Contracts\StatisticsProvider;
use Modules\Core\App\Domain\Actions\SaveStudent;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Platform\App\Domain\Models\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * Helpers shared by the Statistik & Laporan tests: reports and statistics
 * a module could register through Core's contracts, a student placed the
 * way the application places one, and a downloaded CSV read back as rows.
 */

abstract class FakeReport implements Report
{
    public function table(ReportPeriod $period): ReportTable
    {
        return new ReportTable($this->definition()->name, ['Kolom'], [['isi']]);
    }
}

final class LibraryLoansReport extends FakeReport
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition('library-loans', 'Perpustakaan', 'Peminjaman Buku', 'Buku yang sedang dipinjam.', order: 5);
    }
}

final class ManagersOnlyReport extends FakeReport
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition('managers-only', 'Perpustakaan', 'Denda', 'Denda keterlambatan.', 'core.master.manage', 6);
    }
}

final class AttendanceMonthlyReport extends FakeReport
{
    public function definition(): ReportDefinition
    {
        return new ReportDefinition('attendance-monthly', 'Kehadiran', 'Rekap Kehadiran Bulanan', 'Hadir, sakit, izin, dan alpa.', order: 50);
    }
}

final class AttendanceStatistics implements StatisticsProvider
{
    public function figures(?ReportPeriod $period): array
    {
        return [new StatFigure('attendance-rate', 'Rata-rata kehadiran', '94,6%', $period?->name)];
    }

    public function panels(?ReportPeriod $period): array
    {
        return [new StatPanel('attendance-trend', 'Kehadiran 6 bulan terakhir', StatPanel::KIND_BARS, [['label' => 'Sep', 'value' => 94.6]])];
    }
}

/**
 * A student saved through SaveStudent, so the class history is written
 * the way the application writes it.
 *
 * @param  array<string, mixed>  $attributes
 */
function placedStudent(Tenant $tenant, string $name, string $nis, ?ClassGroup $class, array $attributes = []): Student
{
    return inSchool($tenant, fn (): Student => app(SaveStudent::class)->handle(null, [
        'name' => $name,
        'nis' => $nis,
        'gender' => 'L',
        'class_id' => $class?->id,
        ...$attributes,
    ]));
}

/**
 * The rows of a downloaded report, without the `sep=` line.
 *
 * @param  TestResponse<StreamedResponse>  $response
 * @return list<list<string|null>>
 */
function csvRows(TestResponse $response): array
{
    $lines = explode("\r\n", trim($response->streamedContent()));

    expect(array_shift($lines))->toBe('sep=;');

    return array_map(fn (string $line): array => str_getcsv($line, ';', '"', ''), $lines);
}
