<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Teacher;

final class DeleteTeacher
{
    /**
     * @throws ValidationException when the teacher is a homeroom teacher, a coach or teaches a subject
     */
    public function handle(Teacher $teacher): void
    {
        if ($teacher->homeroomClasses()->exists() || $teacher->coachedActivities()->exists() || $teacher->teachingAssignments()->exists()) {
            throw ValidationException::withMessages([
                'status' => "{$teacher->name} masih menjadi wali kelas, pembina ekstrakurikuler, atau pengampu mata pelajaran dan tidak bisa dihapus.",
            ]);
        }

        $teacher->delete();
    }
}
