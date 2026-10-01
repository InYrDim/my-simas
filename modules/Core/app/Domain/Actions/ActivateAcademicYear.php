<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;

/**
 * Makes one year the active one. At most one year is active per school:
 * the previous active year is archived in the same transaction.
 */
final class ActivateAcademicYear
{
    /**
     * @throws ValidationException when the year is already archived
     */
    public function handle(AcademicYear $year): AcademicYear
    {
        if ($year->status === AcademicYearStatus::Archived) {
            throw ValidationException::withMessages([
                'status' => 'Tahun ajaran yang sudah diarsipkan tidak bisa diaktifkan kembali.',
            ]);
        }

        DB::transaction(function () use ($year): void {
            AcademicYear::query()
                ->where('status', AcademicYearStatus::Active->value)
                ->whereKeyNot($year->id)
                ->update(['status' => AcademicYearStatus::Archived->value]);

            $year->forceFill(['status' => AcademicYearStatus::Active])->save();
        });

        return $year;
    }
}
