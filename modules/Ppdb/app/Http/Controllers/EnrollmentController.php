<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Ppdb\App\Domain\Actions\EnrollApplicant;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Http\Requests\EnrollmentRequest;

/**
 * Re-registration of an accepted applicant: the committee gives the NIS and
 * the applicant becomes a student of the school.
 */
final class EnrollmentController
{
    public function store(EnrollmentRequest $request, Applicant $applicant, EnrollApplicant $enroll): RedirectResponse
    {
        $enroll->handle($applicant, $request->validated('nis'));

        return back()->with('status', "{$applicant->name} tercatat daftar ulang dan menjadi siswa. Tempatkan di kelas lewat Warga Sekolah › Siswa.");
    }
}
