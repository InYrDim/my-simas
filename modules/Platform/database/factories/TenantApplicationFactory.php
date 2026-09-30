<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\App\Domain\Models\TenantApplication;
use Modules\Platform\App\Domain\Models\TenantApplicationStatus;

/**
 * @extends Factory<TenantApplication>
 */
class TenantApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TenantApplication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_name' => 'SMA '.fake()->company(),
            'desired_slug' => str(fake()->unique()->slug(2))->replace('_', '-')->toString(),
            'timezone' => 'Asia/Jakarta',
            'applicant_name' => fake()->name(),
            'applicant_email' => fake()->unique()->safeEmail(),
            'applicant_message' => fake()->optional()->sentence(),
            'status' => TenantApplicationStatus::Pending,
        ];
    }

    /**
     * Explicit pending state (default, kept for readability).
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantApplicationStatus::Pending,
        ]);
    }
}
