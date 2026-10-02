<?php

namespace Modules\Ppdb\App\Domain\Queries;

use Illuminate\Support\Collection;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;

/**
 * The period a page works with: the one asked for, else the running one,
 * else the newest — so a school still sees its applicants after the period
 * has closed.
 */
final class ChosenPeriod
{
    /**
     * Every period of the school, newest first.
     *
     * @return Collection<int, AdmissionPeriod>
     */
    public function all(): Collection
    {
        return AdmissionPeriod::query()->orderByDesc('entry_year')->orderByDesc('id')->get();
    }

    /**
     * @param  Collection<int, AdmissionPeriod>  $periods
     */
    public function among(Collection $periods, int $requested = 0): ?AdmissionPeriod
    {
        return $periods->firstWhere('id', $requested)
            ?? $periods->first(fn (AdmissionPeriod $period): bool => $period->status === PeriodStatus::Active)
            ?? $periods->first();
    }

    public function forRequest(int $requested = 0): ?AdmissionPeriod
    {
        return $this->among($this->all(), $requested);
    }
}
