<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Platform\App\Domain\Models\ProviderUser;

/**
 * @extends Factory<ProviderUser>
 */
class ProviderUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = ProviderUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password', // bcrypt rounds set in phpunit.xml
            'remember_token' => Str::random(10),
        ];
    }
}
