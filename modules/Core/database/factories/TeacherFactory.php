<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nip' => fake()->unique()->numerify('##################'),
            'nuptk' => fake()->numerify('################'),
            'employment' => 'PNS',
            'duty' => 'Guru Mapel',
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    public function honorary(): static
    {
        return $this->state(fn (): array => ['employment' => 'Honorer', 'nip' => null]);
    }
}
