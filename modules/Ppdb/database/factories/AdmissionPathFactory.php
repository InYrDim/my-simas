<?php

namespace Modules\Ppdb\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;

/**
 * Defaults to a Zonasi path without seats. `period_id` must be given.
 *
 * @extends Factory<AdmissionPath>
 */
class AdmissionPathFactory extends Factory
{
    protected $model = AdmissionPath::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Zonasi',
            'quota' => 0,
            'sort_order' => 0,
        ];
    }

    public function quota(int $quota): static
    {
        return $this->state(fn (): array => ['quota' => $quota]);
    }
}
