<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\TeachingAssignment;

/**
 * Needs `subject`, `teacher` and `classGroup` loaded.
 *
 * @mixin TeachingAssignment
 */
final class TeachingAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'classId' => $this->class_id,
            'class' => $this->classGroup->name,
            'subjectId' => $this->subject_id,
            'subject' => $this->subject->name,
            'teacherId' => $this->teacher_id,
            'teacher' => $this->teacher->name,
            'hours' => $this->hours_per_week,
        ];
    }
}
