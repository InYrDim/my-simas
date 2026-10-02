<?php

namespace Modules\Ppdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;

/**
 * Defaults to a draft period for 2027. It has no paths or waves: those
 * are made by SavePeriod (paths) and the tests (waves).
 *
 * @extends Factory<AdmissionPeriod>
 */
class AdmissionPeriodFactory extends Factory
{
    protected $model = AdmissionPeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'PPDB 2027/2028',
            'entry_year' => 2027,
            'status' => PeriodStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => PeriodStatus::Active]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => PeriodStatus::Closed]);
    }
}
