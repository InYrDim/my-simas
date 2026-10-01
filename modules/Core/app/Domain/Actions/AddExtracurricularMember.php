<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Extracurricular;
use Modules\Core\App\Domain\Models\Student;

/**
 * Adds a student to an extracurricular by NIS (scales to any school size,
 * unlike a pick list of every student).
 */
final class AddExtracurricularMember
{
    /**
     * @throws ValidationException when the NIS is unknown, not active, or already a member
     */
    public function handle(Extracurricular $extracurricular, string $nis): Student
    {
        $student = Student::query()->where('nis', $nis)->first();

        if ($student === null) {
            throw ValidationException::withMessages(['nis' => 'Siswa dengan NIS ini tidak ditemukan.']);
        }

        if (! $student->isActive()) {
            throw ValidationException::withMessages(['nis' => 'Hanya siswa aktif yang bisa menjadi anggota.']);
        }

        if ($extracurricular->memberships()->where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages(['nis' => "{$student->name} sudah menjadi anggota."]);
        }

        $extracurricular->memberships()->create(['student_id' => $student->id]);

        return $student;
    }
}
