<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\PeriodSlot;

/**
 * @extends Factory<PeriodSlot>
 */
class PeriodSlotFactory extends Factory
{
    protected $model = PeriodSlot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day' => 1,
            'start_time' => '07:15:00',
            'end_time' => '08:00:00',
            'type' => PeriodSlot::LESSON,
        ];
    }
}
