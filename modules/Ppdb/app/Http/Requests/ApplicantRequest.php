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
    private ?AdmissionPeriod $period = null;

    private bool $periodLooked = false;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['wave_id' => ['required', 'integer'], ...ApplicantFieldRules::rules($this->period(), $this->route('applicant') instanceof Applicant ? $this->route('applicant') : null)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ApplicantFieldRules::attributes($this->period());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), ...ApplicantFieldRules::messages()];
    }

    /**
     * The answers to the period's custom fields, by field id.
     *
     * @return array<array-key, mixed>
     */
    public function answersData(): array
    {
        $answers = $this->validated('answers', []);

        return is_array($answers) ? $answers : [];
    }

    private function period(): ?AdmissionPeriod
    {
        if (! $this->periodLooked) {
            $applicant = $this->route('applicant');

            $this->period = $applicant instanceof Applicant
                ? AdmissionPeriod::query()->find($applicant->period_id)
                : AdmissionPeriod::active();
            $this->periodLooked = true;
        }

        return $this->period;
    }
}
