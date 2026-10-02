<?php

namespace Modules\Ppdb\App\Http\Requests\Account;

use Modules\Ppdb\App\Http\Requests\ApplicantFieldRules;
use Modules\Ppdb\App\Http\Requests\PpdbFormRequest;

/**
 * The applicant's own registration form. There is no wave field: the wave
 * open today is the applicant's, and the action decides which that is.
 */
final class ApplicationRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ApplicantFieldRules::rules();
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
