<?php

namespace Modules\Ppdb\App\Domain\Queries;

use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantData;
use Modules\Platform\App\Contracts\TenantDirectory;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Domain\Support\SchoolDay;

/**
 * What an applicant's own page shows: where they stand — not in a school
 * yet, in one but without a form, or with a registration — as plain data
 * for the page. It enters the school explicitly (never through whatever
 * school the request happens to remember) and reads only what belongs to
 * this account.
 *
 * The selection outcome is shown only once the school has announced its
 * results; before that the page says so and nothing more.
 *
 * @phpstan-type Registration array{open: bool, waveName: string|null, closesOn: string|null, nextOpensOn: string|null}
 */
final class PortalState
{
    public function __construct(
        private readonly TenantDirectory $tenants,
        private readonly TenantModules $modules,
        private readonly TenantContext $context,
        private readonly SchoolDay $day,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(PpdbAccount $account): array
    {
        $tenant = $account->tenant_id === null ? null : ($this->tenants->findMany([$account->tenant_id])[$account->tenant_id] ?? null);

        if ($tenant === null) {
            return ['state' => 'no_school'];
        }

        return $this->context->run($tenant->id, fn (): array => $this->inSchool($account, $tenant));
    }

    /**
     * @return array<string, mixed>
     */
    private function inSchool(PpdbAccount $account, TenantData $tenant): array
    {
        $application = Applicant::query()->where('account_id', $account->id)->first();

        if ($application !== null) {
            return ['state' => 'applied', 'school' => $tenant->name, 'application' => $this->describe($application)];
        }

        $period = $tenant->status->value === 'active' && $this->modules->isEnabled('ppdb', $tenant->id)
            ? AdmissionPeriod::active()
            : null;

        if ($period === null) {
            return ['state' => 'unavailable', 'school' => $tenant->name];
        }

        return [
            'state' => 'joined',
            'school' => $tenant->name,
            'period' => $period->name,
            'registration' => $this->registration($period),
        ];
    }

    /**
     * Whether a wave is open today, and the dates the applicant needs.
     *
     * @return array{open: bool, waveName: string|null, closesOn: string|null, nextOpensOn: string|null}
     */
    private function registration(AdmissionPeriod $period): array
    {
        $today = $this->day->today();
        $open = $period->waves->first(fn (AdmissionWave $wave): bool => $wave->statusOn($today) === AdmissionWave::OPEN);
        $next = $period->waves->first(fn (AdmissionWave $wave): bool => $wave->statusOn($today) === AdmissionWave::UPCOMING);

        return [
            'open' => $open !== null,
            'waveName' => $open?->name,
            'closesOn' => $open === null ? null : $this->day->label($open->closes_on),
            'nextOpensOn' => $next === null ? null : $this->day->label($next->opens_on),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Applicant $application): array
    {
        $period = AdmissionPeriod::query()->find($application->period_id);
        $published = $period?->results_published_at !== null;

        return [
            'number' => $application->number,
            'name' => $application->name,
            'pathName' => AdmissionPath::query()->find($application->path_id)?->name,
            'waveName' => AdmissionWave::query()->find($application->wave_id)?->name,
            'registeredOn' => $this->day->label($application->registered_on),
            'status' => $application->status->value,
            'statusLabel' => $application->status->label(),
            'note' => $application->status === ApplicantStatus::Revision ? $application->verification_note : null,
            'canEdit' => $application->status === ApplicantStatus::Revision && ! $application->isEnrolled(),
            'resultsPublished' => $published,
            'decision' => $published ? $application->decision->value : null,
            'decisionLabel' => $published ? $application->decision->label() : null,
            'enrolled' => $application->isEnrolled(),
        ];
    }
}
