<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Enums\SchoolLevel;
use Modules\Core\App\Domain\Models\SchoolProfile;

/**
 * @extends Factory<SchoolProfile>
 */
class SchoolProfileFactory extends Factory
{
    protected $model = SchoolProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level' => SchoolLevel::Sma,
            'npsn' => fake()->numerify('########'),
            'ownership' => 'Swasta',
            'accreditation' => 'A',
            'address' => fake()->address(),
            'phone' => fake()->numerify('(022) 555-####'),
            'email' => fake()->safeEmail(),
            'headmaster' => fake()->name(),
            'headmaster_nip' => fake()->numerify('##################'),
        ];
    }

    public function level(SchoolLevel $level): static
    {
        return $this->state(fn (): array => ['level' => $level]);
    }
}
