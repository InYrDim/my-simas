<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;

final class DeleteAcademicYear
{
    /**
     * Only a draft year can be removed; active and archived years carry
     * history the school still needs.
     *
     * @throws ValidationException
     */
    public function handle(AcademicYear $year): void
    {
        if ($year->status !== AcademicYearStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Hanya tahun ajaran berstatus draf yang bisa dihapus.',
            ]);
        }

        DB::transaction(function () use ($year): void {
            $year->semesters()->delete();
            $year->delete();
        });
    }
}
