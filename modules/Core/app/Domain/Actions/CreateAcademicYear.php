<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Models\AcademicYear;

/**
 * Creates a draft academic year with its Ganjil and Genap semesters.
 * The semester dates are a sensible split of the year (Ganjil runs
 * about 23 weeks, a two-week break, Genap runs to the year's end);
 * the school fine-tunes them on the Semester page.
 */
final class CreateAcademicYear
{
    /**
     * @param  array{name: string, curriculum: string, start_date: string, end_date: string}  $data
     */
    public function handle(array $data): AcademicYear
    {
        return DB::transaction(function () use ($data): AcademicYear {
            $year = AcademicYear::query()->create($data);

            $start = Carbon::parse($data['start_date']);
            $end = Carbon::parse($data['end_date']);

            $ganjilEnd = $start->copy()->addWeeks(22)->addDays(5)->min($end);
            $genapStart = $ganjilEnd->copy()->addDays(16)->min($end);

            $year->semesters()->create([
                'name' => 'Ganjil',
                'start_date' => $start->toDateString(),
                'end_date' => $ganjilEnd->toDateString(),
            ]);
            $year->semesters()->create([
                'name' => 'Genap',
                'start_date' => $genapStart->toDateString(),
                'end_date' => $end->toDateString(),
            ]);

            return $year;
        });
    }
}
