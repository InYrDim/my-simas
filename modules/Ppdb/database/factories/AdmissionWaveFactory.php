<?php

namespace Modules\Ppdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ppdb\App\Domain\Models\AdmissionWave;

/**
 * Defaults to the first quarter of 2027. `period_id` must be given.
 *
 * @extends Factory<AdmissionWave>
 */
class AdmissionWaveFactory extends Factory
{
    protected $model = AdmissionWave::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Gelombang 1',
            'opens_on' => '2027-01-01',
            'closes_on' => '2027-03-31',
        ];
    }

    public function between(string $opensOn, string $closesOn): static
    {
        return $this->state(fn (): array => ['opens_on' => $opensOn, 'closes_on' => $closesOn]);
    }
}
