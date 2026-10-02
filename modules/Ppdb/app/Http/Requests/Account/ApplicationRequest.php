<?php

namespace Modules\Ppdb\App\Http\Requests\Account;

use Illuminate\Support\Facades\Auth;
use Modules\Platform\App\Contracts\TenantContext;
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
     * @var array{rules: array<string, mixed>, attributes: array<string, string>}|null
     */
    private ?array $form = null;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->form()['rules'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->form()['attributes'];
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

    /**
     * The rules and names of the period's form; the usual form when the
     * account has no school or the school no period.
     *
     * @return array{rules: array<string, mixed>, attributes: array<string, string>}
     */
    private function form(): array
    {
        if ($this->form !== null) {
            return $this->form;
        }

        $account = Auth::guard('ppdb')->user();

        if (! $account instanceof PpdbAccount || $account->tenant_id === null) {
            return $this->form = ['rules' => ApplicantFieldRules::rules(), 'attributes' => ApplicantFieldRules::attributes()];
        }

        return $this->form = app(TenantContext::class)->run($account->tenant_id, function () use ($account): array {
            $application = Applicant::query()->where('account_id', $account->id)->first();

            $period = $application === null
                ? AdmissionPeriod::active()
                : AdmissionPeriod::query()->find($application->period_id);

            return ['rules' => ApplicantFieldRules::rules($period, $application), 'attributes' => ApplicantFieldRules::attributes($period)];
        });
    }
}
