<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\Room;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'R-'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Ruang Kelas '.fake()->numberBetween(100, 999),
            'type' => 'Kelas',
            'capacity' => 36,
            'status' => 'active',
        ];
    }

    public function underMaintenance(): static
    {
        return $this->state(fn (): array => ['status' => 'maintenance']);
    }
}
