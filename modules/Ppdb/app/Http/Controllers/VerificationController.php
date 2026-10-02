<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Ppdb\App\Domain\Actions\VerifyApplicant;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Http\Requests\VerificationRequest;

/**
 * The committee's verification of one applicant.
 */
final class VerificationController
{
    public function update(VerificationRequest $request, Applicant $applicant, VerifyApplicant $verify): RedirectResponse
    {
        $verify->handle(
            $applicant,
            ApplicantStatus::from($request->validated('status')),
            $request->validated('verification_note'),
        );

        return back()->with('status', 'Status verifikasi disimpan.');
    }
}
