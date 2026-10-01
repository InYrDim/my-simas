<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Teacher;

final class DeleteTeacher
{
    /**
     * @throws ValidationException when the teacher is a homeroom teacher or a coach
     */
    public function handle(Teacher $teacher): void
    {
        if ($teacher->homeroomClasses()->exists() || $teacher->coachedActivities()->exists()) {
            throw ValidationException::withMessages([
                'status' => "{$teacher->name} masih menjadi wali kelas atau pembina ekstrakurikuler dan tidak bisa dihapus.",
            ]);
        }

        $teacher->delete();
    }
}
