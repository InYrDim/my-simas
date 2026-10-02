<?php

namespace Modules\Attendance\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Attendance\App\Domain\Models\LessonSession;

/**
 * `class_id` and `period_slot_id` are Core's ids and must be given.
 *
 * @extends Factory<LessonSession>
 */
class LessonSessionFactory extends Factory
{
    protected $model = LessonSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'start_time' => '07:15:00',
            'end_time' => '08:00:00',
        ];
    }
}
