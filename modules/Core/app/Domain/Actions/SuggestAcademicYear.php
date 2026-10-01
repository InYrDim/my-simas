<?php

namespace Modules\Core\App\Domain\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\Core\App\Domain\Models\AcademicYear;

/**
 * Proposes the next academic year for the "Tambah tahun ajaran" form: the
 * latest year shifted by one year, or — for a school with none — the year
 * that is running on `$today` (Indonesian school years start in July).
 * The name is moved forward until it is free.
 */
final class SuggestAcademicYear
{
    private const DEFAULT_CURRICULUM = 'Kurikulum Merdeka';

    /**
     * @return array{name: string, curriculum: string, start_date: string, end_date: string}
     */
    public function handle(CarbonInterface $today): array
    {
        $latest = AcademicYear::query()->orderByDesc('start_date')->first();

        if ($latest === null) {
            $startYear = $today->month >= 7 ? $today->year : $today->year - 1;
            $start = Carbon::create($startYear, 7, 13);
            $end = Carbon::create($startYear + 1, 6, 26);
            $curriculum = self::DEFAULT_CURRICULUM;
        } else {
            $start = $latest->start_date->copy()->addYear();
            $end = $latest->end_date->copy()->addYear();
            $curriculum = $latest->curriculum;
        }

        $taken = AcademicYear::query()->pluck('name')->all();

        while (in_array($this->nameFor($start, $end), $taken, true)) {
            $start = $start->copy()->addYear();
            $end = $end->copy()->addYear();
        }

        return [
            'name' => $this->nameFor($start, $end),
            'curriculum' => $curriculum,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ];
    }

    private function nameFor(CarbonInterface $start, CarbonInterface $end): string
    {
        return $start->year.'/'.$end->year;
    }
}
