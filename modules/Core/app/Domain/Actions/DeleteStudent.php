<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Models\ExtracurricularMember;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\StudentClassHistory;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\Exceptions\AccountActionRefusedException;

/**
 * Deletes a student with the class history and memberships. The account
 * is not deleted: it is deactivated and stays in the user list.
 */
final class DeleteStudent
{
    public function __construct(private readonly AccountProvisioner $accounts) {}

    public function handle(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            StudentClassHistory::query()->where('student_id', $student->id)->delete();
            ExtracurricularMember::query()->where('student_id', $student->id)->delete();
            $student->delete();

            if ($student->user_id !== null) {
                try {
                    $this->accounts->deactivate($student->user_id);
                } catch (AccountActionRefusedException) {
                    // The account is gone or protected; the record is deleted either way.
                }
            }
        });
    }
}
