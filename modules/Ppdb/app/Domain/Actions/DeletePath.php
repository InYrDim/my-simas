<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Deletes a path nobody has applied through. A period keeps at least one
 * path, or nobody could register.
 */
final class DeletePath
{
    /**
     * @throws ValidationException when the path has applicants or is the period's last
     */
    public function handle(AdmissionPath $path): void
    {
        if (Applicant::query()->where('path_id', $path->id)->exists()) {
            throw ValidationException::withMessages(['path' => "Jalur {$path->name} sudah punya pendaftar dan tidak bisa dihapus."]);
        }

        if (AdmissionPath::query()->where('period_id', $path->period_id)->count() <= 1) {
            throw ValidationException::withMessages(['path' => 'Periode harus punya sedikitnya satu jalur.']);
        }

        $path->delete();
    }
}
