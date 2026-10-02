<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\ApplicantChecks;

/**
 * An applicant correcting their own registration. Allowed only while the
 * committee asked for a correction; saving sends it back to the committee
 * as waiting for verification (the note they wrote is cleared). The wave
 * stays the one the applicant registered in.
 */
final class UpdateOwnApplication
{
    public function __construct(
        private readonly ApplicantChecks $checks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Applicant $applicant, array $data): Applicant
    {
        if ($applicant->status !== ApplicantStatus::Revision || $applicant->isEnrolled()) {
            throw ValidationException::withMessages(['application' => 'Data hanya bisa diperbaiki saat panitia meminta perbaikan.']);
        }

        $data = Arr::only($data, Applicant::DATA_FIELDS);
        $data['wave_id'] = $applicant->wave_id;

        $period = AdmissionPeriod::query()->findOrFail($applicant->period_id);

        $this->checks->choices($period, $data, $applicant);

        $applicant->fill($data)->forceFill([
            'status' => ApplicantStatus::Submitted,
            'verification_note' => null,
        ])->save();

        return $applicant;
    }
}
