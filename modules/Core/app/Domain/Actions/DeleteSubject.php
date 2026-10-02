<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Subject;

final class DeleteSubject
{
    /**
     * @throws ValidationException when a teacher is still assigned to the subject
     */
    public function handle(Subject $subject): void
    {
        if ($subject->teachingAssignments()->exists()) {
            throw ValidationException::withMessages([
                'status' => "Mata pelajaran {$subject->name} masih memiliki guru pengampu dan tidak bisa dihapus.",
            ]);
        }

        $subject->delete();
    }
}
