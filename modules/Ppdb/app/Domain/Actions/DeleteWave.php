<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Deletes a wave nobody has registered in.
 */
final class DeleteWave
{
    /**
     * @throws ValidationException when the wave has applicants
     */
    public function handle(AdmissionWave $wave): void
    {
        if (Applicant::query()->where('wave_id', $wave->id)->exists()) {
            throw ValidationException::withMessages(['wave' => "{$wave->name} sudah punya pendaftar dan tidak bisa dihapus."]);
        }

        $wave->delete();
    }
}
