<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Platform\App\Domain\Models\Applicant;

/**
 * @extends Factory<Applicant>
 */
class ApplicantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Applicant::class;

    /**
     * Define the model's default state: a verified applicant.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password', // bcrypt rounds set in phpunit.xml
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An applicant who has not opened the verification link yet.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
