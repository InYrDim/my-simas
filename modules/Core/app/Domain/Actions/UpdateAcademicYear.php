<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\AcademicYear;

final class UpdateAcademicYear
{
    /**
     * @param  array{name: string, curriculum: string, start_date: string, end_date: string}  $data
     *
     * @throws ValidationException when a semester would fall outside the new range
     */
    public function handle(AcademicYear $year, array $data): AcademicYear
    {
        $outside = $year->semesters()
            ->where(fn ($query) => $query
                ->where('start_date', '<', $data['start_date'])
                ->orWhere('end_date', '>', $data['end_date']))
            ->exists();

        if ($outside) {
            throw ValidationException::withMessages([
                'start_date' => 'Ada semester yang berada di luar rentang tanggal ini. Ubah tanggal semester lebih dulu.',
            ]);
        }

        $year->fill($data)->save();

        return $year;
    }
}
