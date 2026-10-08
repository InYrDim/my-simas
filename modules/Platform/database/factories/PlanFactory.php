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
            'limits' => null,
            'modules' => ['core', 'identity'],
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
            'archived_at' => null,
        ];
    }

    /**
     * @param  array<string, int>  $limits  students, staff_accounts, storage_mb
     */
    public function withLimits(array $limits): static
    {
        return $this->state(fn (): array => ['limits' => $limits]);
    }

    /**
     * A plan only the provider assigns, hidden from the sign-up list.
     */
    public function private(): static
    {
        return $this->state(fn (): array => ['is_public' => false]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
            'archived_at' => now(),
        ]);
    }
}
