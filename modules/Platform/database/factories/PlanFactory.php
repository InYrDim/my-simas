<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\App\Domain\Models\Plan;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $monthly = fake()->numberBetween(10, 100) * 10_000;

        return [
            'key' => str(fake()->unique()->slug(2))->toString(),
            'name' => ucfirst(fake()->unique()->word()),
            'price_monthly' => $monthly,
            'price_yearly' => $monthly * 10,
            'max_users' => 50,
            'modules' => ['core', 'identity'],
            'is_active' => true,
            'sort_order' => 0,
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'archived_at' => now(),
        ]);
    }
}
