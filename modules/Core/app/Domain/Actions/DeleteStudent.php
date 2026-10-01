<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Models\ExtracurricularMember;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\StudentClassHistory;

final class DeleteStudent
{
    public function handle(Student $student): void
    {
        DB::transaction(function () use ($student): void {
            StudentClassHistory::query()->where('student_id', $student->id)->delete();
            ExtracurricularMember::query()->where('student_id', $student->id)->delete();
            $student->delete();
        });
    }
}
