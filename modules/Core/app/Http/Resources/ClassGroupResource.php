<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\ClassGroup;

/**
 * Needs `grade`, `major`, `room`, `homeroom` and `academicYear` loaded;
 * `students_count` is the number of active students, when counted.
 *
 * @mixin ClassGroup
 */
final class ClassGroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'gradeId' => $this->grade_id,
            'grade' => $this->grade->name,
            'majorId' => $this->major_id,
            'major' => $this->major?->code,
            'homeroom' => $this->homeroom?->name,
            'homeroomId' => $this->homeroom_teacher_id,
            'roomId' => $this->room_id,
            'room' => $this->room?->name,
            'students' => (int) ($this->students_count ?? 0),
            'yearId' => $this->academic_year_id,
            'year' => $this->academicYear->name,
        ];
    }
}
