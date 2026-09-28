<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\TenantModules;

/**
 * @extends Factory<TenantModules>
 */
class TenantModulesFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = TenantModules::class;

    /**
     * Define the model's default state. tenant_id must be provided via
     * ->for(Tenant::factory()) or ->for($tenant) by the caller.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module' => 'core',
            'enabled' => true,
            'enabled_at' => now(),
            'expires_at' => null,
            'meta' => null,
        ];
    }
}
