<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\TimetableEntry;

/**
 * @extends Factory<TimetableEntry>
 */
class TimetableEntryFactory extends Factory
{
    protected $model = TimetableEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_slot_id' => PeriodSlot::factory(),
            'class_id' => ClassGroup::factory(),
            'subject_id' => Subject::factory(),
        ];
    }
}
