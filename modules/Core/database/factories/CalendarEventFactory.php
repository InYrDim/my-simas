<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\CalendarEvent;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    protected $model = CalendarEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'category' => 'activity',
            'start_date' => '2026-03-16',
            'end_date' => null,
        ];
    }

    public function category(string $category): static
    {
        return $this->state(fn (): array => ['category' => $category]);
    }
}
