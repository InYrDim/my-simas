<?php

namespace Modules\Ppdb\App\Domain\Queries;

use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * The admissions funnel of a period — registered, verified, accepted,
 * re-registered — and how many applicants each wave has.
 */
final class AdmissionFunnel
{
    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    public function forPeriod(AdmissionPeriod $period): array
    {
        $row = Applicant::query()
            ->where('period_id', $period->id)
            ->selectRaw("count(*) as submitted, coalesce(sum(case when status = 'verified' then 1 else 0 end), 0) as verified, coalesce(sum(case when decision = 'accepted' then 1 else 0 end), 0) as accepted, coalesce(sum(case when enrolled_at is not null then 1 else 0 end), 0) as registered")
            ->toBase()
            ->first();

        return [
            ['key' => 'submitted', 'label' => 'Mendaftar', 'count' => (int) $row->submitted],
            ['key' => 'verified', 'label' => 'Berkas terverifikasi', 'count' => (int) $row->verified],
            ['key' => 'accepted', 'label' => 'Diterima', 'count' => (int) $row->accepted],
            ['key' => 'registered', 'label' => 'Daftar ulang', 'count' => (int) $row->registered],
        ];
    }

    /**
     * @return array<int, int> applicants per wave id
     */
    public function perWave(AdmissionPeriod $period): array
    {
        return Applicant::query()
            ->where('period_id', $period->id)
            ->selectRaw('wave_id, count(*) as total')
            ->groupBy('wave_id')
            ->toBase()
            ->pluck('total', 'wave_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
