<?php

namespace Modules\Ppdb\App\Http\Requests;

use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * The fields of an applicant, as the committee enters or changes them,
 * under the form of the applicant's period (the running one for a new
 * applicant). Whether the wave and the path belong to the period is the
 * Action's business: it knows the period.
 */
final class ApplicantRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $applicant = $this->route('applicant');
        $period = $applicant instanceof Applicant
            ? AdmissionPeriod::query()->find($applicant->period_id)
            : AdmissionPeriod::active();

        return ['wave_id' => ['required', 'integer'], ...ApplicantFieldRules::rules($period?->formFields())];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ApplicantFieldRules::attributes();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), ...ApplicantFieldRules::messages()];
    }
}
