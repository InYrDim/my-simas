<?php

namespace Modules\Core\App\Domain\Queries;

use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * The student or teacher record whose `user_id` is the signed-in account,
 * as plain rows for the "Saya" pages. Both models are tenant-scoped, so
 * another school's record can never match.
 */
final class SignedInPerson
{
    /**
     * @return array{kind: 'student', name: string, nis: string, nisn: string|null, gender: string, birthDate: string|null, class: string|null, guardianName: string|null}|array{kind: 'teacher', name: string, nip: string|null, nuptk: string|null, employment: string, duty: string, email: string|null}|null
     */
    public function forUser(?int $userId): ?array
    {
        if ($userId === null) {
            return null;
        }

        $student = Student::query()->with('classGroup')->where('user_id', $userId)->first();

        if ($student !== null) {
            return [
                'kind' => 'student',
                'name' => $student->name,
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'gender' => $student->gender,
                'birthDate' => $student->birth_date?->toDateString(),
                'class' => $student->classGroup?->name,
                'guardianName' => $student->guardian_name,
            ];
        }

        $teacher = Teacher::query()->where('user_id', $userId)->first();

        return $teacher === null ? null : [
            'kind' => 'teacher',
            'name' => $teacher->name,
            'nip' => $teacher->nip,
            'nuptk' => $teacher->nuptk,
            'employment' => $teacher->employment,
            'duty' => $teacher->duty,
            'email' => $teacher->email,
        ];
    }
}
