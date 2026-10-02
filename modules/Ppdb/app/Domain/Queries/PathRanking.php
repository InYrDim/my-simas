<?php

namespace Modules\Ppdb\App\Domain\Queries;

use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * The selection ranking: the verified applicants of one path, highest
 * score first (those without a score last, by registration number), and
 * how the decisions stand in every path of the period.
 */
final class PathRanking
{
    /**
     * @return list<array{applicant: Applicant, rank: int|null}> rank counts the applicants that have a score; null without one
     */
    public function forPath(AdmissionPath $path): array
    {
        // Applicants with a score come first, so a scored applicant's rank is
        // simply their place in the list.
        $applicants = Applicant::query()
            ->where('path_id', $path->id)
            ->where('status', 'verified')
            ->orderByRaw('score is null')
            ->orderByDesc('score')
            ->orderBy('number')
            ->get()
            ->values();

        return array_values($applicants->map(fn (Applicant $applicant, int $index): array => [
            'applicant' => $applicant,
            'rank' => $applicant->score === null ? null : $index + 1,
        ])->all());
    }

    /**
     * Per path id: the verified applicants and how many of them are
     * accepted, on the waiting list, or not decided yet.
     *
     * @return array<int, array{verified: int, accepted: int, waitlist: int, pending: int}>
     */
    public function counts(AdmissionPeriod $period): array
    {
        return Applicant::query()
            ->where('period_id', $period->id)
            ->where('status', 'verified')
            ->selectRaw("path_id, count(*) as verified, coalesce(sum(case when decision = 'accepted' then 1 else 0 end), 0) as accepted, coalesce(sum(case when decision = 'waitlist' then 1 else 0 end), 0) as waitlist, coalesce(sum(case when decision = 'pending' then 1 else 0 end), 0) as pending")
            ->groupBy('path_id')
            ->toBase()
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->path_id => [
                'verified' => (int) $row->verified,
                'accepted' => (int) $row->accepted,
                'waitlist' => (int) $row->waitlist,
                'pending' => (int) $row->pending,
            ]])
            ->all();
    }
}
