<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantStatus;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Sekolah '.fake()->company(),
            'slug' => str(fake()->unique()->slug(2))->replace('_', '-')->toString(),
            'domain' => null,
            'timezone' => 'Asia/Jakarta',
            'status' => TenantStatus::Active,
            'settings' => null,
            'billing_email' => null,
            'billing_name' => null,
            'billing_exempt' => false,
            'suspended_reason' => null,
        ];
    }

    /**
     * Indicate the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantStatus::Suspended,
            'suspended_reason' => 'manual',
        ]);
    }

    /**
     * Indicate the school is never billed, reminded or suspended for billing.
     */
    public function billingExempt(): static
    {
        return $this->state(fn (array $attributes): array => ['billing_exempt' => true]);
    }
}
