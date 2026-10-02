<?php

namespace Modules\Core\App\Infrastructure\Admission;

use Modules\Core\App\Contracts\DTOs\NewStudent;
use Modules\Core\App\Contracts\Exceptions\StudentAdmissionRefusedException;
use Modules\Core\App\Contracts\StudentAdmission;
use Modules\Core\App\Domain\Actions\SaveStudent;
use Modules\Core\App\Domain\Models\Student;

/**
 * Default StudentAdmission: the same SaveStudent every other way of making
 * a student goes through, after checking that the numbers are free (the
 * unique index would only answer with a database error).
 */
final class DefaultStudentAdmission implements StudentAdmission
{
    public function __construct(
        private readonly SaveStudent $save,
    ) {}

    public function admit(NewStudent $student): int
    {
        if (Student::query()->where('nis', $student->nis)->exists()) {
            throw new StudentAdmissionRefusedException('nis', 'NIS ini sudah terdaftar.');
        }

        if ($student->nisn !== null && Student::query()->where('nisn', $student->nisn)->exists()) {
            throw new StudentAdmissionRefusedException('nisn', 'NISN ini sudah terdaftar.');
        }

        return $this->save->handle(null, [
            'name' => $student->name,
            'nis' => $student->nis,
            'nisn' => $student->nisn,
            'gender' => $student->gender,
            'birth_date' => $student->birthDate,
            'guardian_name' => $student->guardianName,
            'guardian_phone' => $student->guardianPhone,
            'class_id' => null,
        ])->id;
    }
}
