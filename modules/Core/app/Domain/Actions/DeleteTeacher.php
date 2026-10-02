<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\Exceptions\AccountActionRefusedException;

/**
 * Deletes a teacher. The linked account is not deleted: it is
 * deactivated, except the school's last active admin, who keeps access.
 */
final class DeleteTeacher
{
    public function __construct(private readonly AccountProvisioner $accounts) {}

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

        DB::transaction(function () use ($teacher): void {
            $teacher->delete();

            if ($teacher->user_id !== null) {
                try {
                    $this->accounts->deactivate($teacher->user_id);
                } catch (AccountActionRefusedException) {
                    // The last active admin keeps the account; the record is deleted either way.
                }
            }
        });
    }
}
