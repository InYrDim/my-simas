<?php

namespace Modules\Ppdb\App\Http\Requests;

use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\FormRules;

/**
 * The rules and names of an applicant's own fields, shared by the
 * committee's form and the applicant's own form (which has no wave: the
 * wave open today is the applicant's). Which fields are asked, and whether
 * they are required, is the period's form.
 */
final class ApplicantFieldRules
{
    /**
     * @param  AdmissionPeriod|null  $period  the form's period; the usual form when there is none
     * @param  Applicant|null  $applicant  who is changing their registration (a file they sent already counts as given)
     * @return array<string, mixed>
     */
    public static function rules(?AdmissionPeriod $period = null, ?Applicant $applicant = null): array
    {
        return app(FormRules::class)->forPeriod($period, $applicant);
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(?AdmissionPeriod $period = null): array
    {
        return app(FormRules::class)->attributesFor($period);
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.'];
    }
}
