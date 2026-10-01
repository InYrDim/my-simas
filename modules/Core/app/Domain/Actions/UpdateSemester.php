<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Semester;

final class UpdateSemester
{
    /**
     * @param  array{start_date: string, end_date: string}  $data
     *
     * @throws ValidationException when the dates leave the academic year or overlap the sibling semester
     */
    public function handle(Semester $semester, array $data): Semester
    {
        $year = $semester->academicYear;

        if ($data['start_date'] < $year->start_date->toDateString() || $data['end_date'] > $year->end_date->toDateString()) {
            throw ValidationException::withMessages([
                'start_date' => "Tanggal semester harus berada dalam tahun ajaran {$year->name}.",
            ]);
        }

        $overlaps = $year->semesters()
            ->whereKeyNot($semester->id)
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_date' => 'Tanggal semester tidak boleh tumpang tindih dengan semester lain pada tahun ajaran ini.',
            ]);
        }

        $semester->fill($data)->save();

        return $semester;
    }
}
