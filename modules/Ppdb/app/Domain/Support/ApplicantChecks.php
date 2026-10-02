<?php

namespace Modules\Ppdb\App\Domain\Support;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * The rules about an applicant's choices that registering and changing an
 * applicant share: the wave and the path belong to the period, and the
 * NISN is not already taken in it.
 */
final class ApplicantChecks
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function choices(AdmissionPeriod $period, array $data, ?Applicant $except = null): void
    {
        if (! $period->waves()->whereKey($data['wave_id'])->exists()) {
            throw ValidationException::withMessages(['wave_id' => 'Gelombang tidak ditemukan di periode ini.']);
        }

        if (! $period->paths()->whereKey($data['path_id'])->exists()) {
            throw ValidationException::withMessages(['path_id' => 'Jalur tidak ditemukan di periode ini.']);
        }

        $nisn = $data['nisn'] ?? null;

        if ($nisn !== null && Applicant::query()
            ->where('period_id', $period->id)
            ->where('nisn', $nisn)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))
            ->exists()) {
            throw ValidationException::withMessages(['nisn' => 'NISN ini sudah terdaftar di periode ini.']);
        }
    }
}
