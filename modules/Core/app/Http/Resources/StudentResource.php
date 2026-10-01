<?php

namespace Modules\Core\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\App\Domain\Models\Student;

/**
 * Needs `classGroup` loaded.
 *
 * @mixin Student
 */
final class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nis' => $this->nis,
            'nisn' => $this->nisn,
            'gender' => $this->gender,
            'birth' => $this->birth_date?->toDateString(),
            'class' => $this->classGroup?->name,
            'classId' => $this->class_id,
            'status' => $this->status,
            'guardian' => $this->guardian_name,
            'guardianPhone' => $this->guardian_phone,
            'hasAccount' => $this->user_id !== null,
        ];
    }
}
