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
        ];
    }

    /**
     * Indicate the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TenantStatus::Suspended,
        ]);
    }

    /**
     * Assign a custom domain to the tenant.
     */
    public function withDomain(?string $domain): static
    {
        return $this->state(fn (array $attributes): array => [
            'domain' => $domain,
        ]);
    }
}
