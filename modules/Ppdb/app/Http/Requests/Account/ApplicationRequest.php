<?php

namespace Modules\Ppdb\App\Http\Requests\Account;

use Illuminate\Support\Facades\Auth;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Ppdb\App\Domain\Enums\FieldRequirement;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Http\Requests\ApplicantFieldRules;
use Modules\Ppdb\App\Http\Requests\PpdbFormRequest;

/**
 * The applicant's own registration form. There is no wave field: the wave
 * open today is the applicant's, and the action decides which that is.
 *
 * The fields asked are those of the period the applicant registers in (the
 * running one) or, to correct a registration, of the period it is in. This
 * is a central page with no school of its own, so the period is looked up
 * inside the school the account joined.
 */
final class ApplicationRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ApplicantFieldRules::rules($this->formFields());
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

    /**
     * @return array<string, FieldRequirement>|null null when the account has no school or the school no period
     */
    private function formFields(): ?array
    {
        $account = Auth::guard('ppdb')->user();

        if (! $account instanceof PpdbAccount || $account->tenant_id === null) {
            return null;
        }

        return app(TenantContext::class)->run($account->tenant_id, function () use ($account): ?array {
            $application = Applicant::query()->where('account_id', $account->id)->first();

            $period = $application === null
                ? AdmissionPeriod::active()
                : AdmissionPeriod::query()->find($application->period_id);

            return $period?->formFields();
        });
    }
}
