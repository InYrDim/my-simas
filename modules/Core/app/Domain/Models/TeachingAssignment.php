<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Database\Factories\TeachingAssignmentFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A teacher teaching one subject in one class, for the class's academic
 * year. Stored in `teaching_assignments`.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $class_id
 * @property int $subject_id
 * @property int $teacher_id
 * @property int $hours_per_week
 */
#[UseFactory(TeachingAssignmentFactory::class)]
#[Fillable(['class_id', 'subject_id', 'teacher_id', 'hours_per_week'])]
class TeachingAssignment extends Model
{
    /** @use HasFactory<TeachingAssignmentFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<ClassGroup, $this>
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }
}
