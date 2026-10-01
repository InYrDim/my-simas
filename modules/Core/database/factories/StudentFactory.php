<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\Student;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'nis' => fake()->unique()->numerify('24####'),
            'nisn' => fake()->unique()->numerify('00########'),
            'gender' => fake()->randomElement(['L', 'P']),
            'birth_date' => fake()->date('Y-m-d', '2012-12-31'),
            'guardian_name' => fake()->name(),
            'guardian_phone' => fake()->numerify('0812-5550-####'),
            'status' => 'active',
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn (): array => ['status' => $status, 'class_id' => null]);
    }
}
