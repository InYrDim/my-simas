<?php

namespace Modules\Ppdb\App\Domain\Queries;

use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * The applicants of a period whose data the committee has not checked yet:
 * how many, and the ones who have waited longest.
 */
final class AwaitingVerification
{
    /**
     * @return array{count: int, applicants: list<array{id: int, name: string, number: string, registeredOn: string}>}
     */
    public function forPeriod(AdmissionPeriod $period, int $limit = 5): array
    {
        $waiting = Applicant::query()
            ->where('period_id', $period->id)
            ->where('status', ApplicantStatus::Submitted->value);

        return [
            'count' => (clone $waiting)->count(),
            'applicants' => $waiting
                ->orderBy('registered_on')
                ->orderBy('id')
                ->limit($limit)
                ->get(['id', 'name', 'number', 'registered_on'])
                ->map(fn (Applicant $applicant): array => [
                    'id' => $applicant->id,
                    'name' => $applicant->name,
                    'number' => $applicant->number,
                    'registeredOn' => $applicant->registered_on,
                ])
                ->all(),
        ];
    }
}
