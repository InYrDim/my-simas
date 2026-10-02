<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;

/**
 * @extends Factory<TeachingAssignment>
 */
class TeachingAssignmentFactory extends Factory
{
    protected $model = TeachingAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id' => ClassGroup::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => Teacher::factory(),
            'hours_per_week' => 2,
        ];
    }
}
