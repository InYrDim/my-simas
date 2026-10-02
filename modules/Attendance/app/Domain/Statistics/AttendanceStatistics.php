<?php

namespace Modules\Attendance\App\Domain\Statistics;

use Modules\Attendance\App\Domain\Models\DailyAttendance;
use Modules\Attendance\App\Domain\Queries\AttendanceTally;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Core\App\Contracts\DTOs\ReportPeriod;
use Modules\Core\App\Contracts\DTOs\StatFigure;
use Modules\Core\App\Contracts\DTOs\StatPanel;
use Modules\Core\App\Contracts\StatisticsProvider;

/**
 * Attendance on the Statistik page: the share of daily records with the
 * student at school (on time or late) over the academic year, and the same
 * share for each of the last six months.
 */
final class AttendanceStatistics implements StatisticsProvider
{
    private const TREND_MONTHS = 6;

    public function __construct(
        private readonly SchoolClock $clock,
    ) {}

    public function figures(?ReportPeriod $period): array
    {
        $percent = $period === null
            ? null
            : array_reduce(
                $this->talliesByMonth($period->startsOn, $period->endsOn),
                function (AttendanceTally $whole, AttendanceTally $month): AttendanceTally {
                    $whole->merge($month);

                    return $whole;
                },
                new AttendanceTally,
            )->percent();

        return [
            new StatFigure(
                'attendance-rate',
                'Rata-rata kehadiran',
                $percent === null ? '—' : "{$percent}%",
                $percent === null ? 'Belum ada catatan' : $period->name,
            ),
        ];
    }

    public function panels(?ReportPeriod $period): array
    {
        $current = $this->clock->now()->startOfMonth();
        $first = $current->subMonths(self::TREND_MONTHS - 1);
        $tallies = $this->talliesByMonth($first->toDateString(), $current->endOfMonth()->toDateString());

        $points = [];

        for ($month = $first; $month <= $current; $month = $month->addMonth()) {
            $points[] = [
                'label' => $this->clock->shortMonth($month),
                'value' => ($tallies[$month->format('Y-m')] ?? new AttendanceTally)->percent() ?? 0,
            ];
        }

        return [
            new StatPanel(
                key: 'attendance-trend',
                title: 'Kehadiran 6 bulan terakhir',
                kind: StatPanel::KIND_BARS,
                points: $points,
                note: 'Persen catatan harian dengan siswa hadir atau terlambat.',
            ),
        ];
    }

    /**
     * Grouped here, not in SQL, so every database gives the same months.
     *
     * @param  string  $from  Y-m-d
     * @param  string  $to  Y-m-d
     * @return array<string, AttendanceTally> keyed by `Y-m`
     */
    private function talliesByMonth(string $from, string $to): array
    {
        $tallies = [];

        DailyAttendance::query()
            ->whereBetween('date', [$from, $to])
            ->get(['date', 'status'])
            ->each(function (DailyAttendance $row) use (&$tallies): void {
                ($tallies[substr($row->date, 0, 7)] ??= new AttendanceTally)->add($row->status);
            });

        return $tallies;
    }
}
