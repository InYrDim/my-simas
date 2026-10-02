<?php

namespace Modules\Ppdb\App\Http\Requests;

/**
 * The fields of an applicant, as the committee enters or changes them.
 * Whether the wave and the path belong to the period is the Action's
 * business: it knows the period.
 */
final class ApplicantRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['wave_id' => ['required', 'integer'], ...ApplicantFieldRules::rules()];
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
