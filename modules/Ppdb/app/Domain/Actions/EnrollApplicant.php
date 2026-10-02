<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Contracts\DTOs\NewStudent;
use Modules\Core\App\Contracts\Exceptions\StudentAdmissionRefusedException;
use Modules\Core\App\Contracts\StudentAdmission;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Records that an accepted applicant has come to re-register: they become
 * a student of the school (through Core, without a class and without a
 * login account — placing them in a class and making the account are the
 * school's usual steps for a student) and the registration remembers the
 * student. Possible only after the results are announced, for an accepted
 * applicant, and once.
 *
 * The NIS is the school's to give, so the committee types it. Core refuses
 * a NIS or NISN the school already uses; nothing is saved then.
 */
final class EnrollApplicant
{
    public function __construct(
        private readonly StudentAdmission $students,
    ) {}

    /**
     * @return int the id of the new student
     *
     * @throws ValidationException
     */
    public function handle(Applicant $applicant, string $nis): int
    {
        $period = AdmissionPeriod::query()->findOrFail($applicant->period_id);

        if ($period->results_published_at === null) {
            throw ValidationException::withMessages(['enroll' => 'Daftar ulang baru bisa dicatat setelah hasil seleksi diumumkan.']);
        }

        return DB::transaction(function () use ($applicant, $nis): int {
            // Read again inside the transaction: two clicks must make one student.
            $current = Applicant::query()->lockForUpdate()->findOrFail($applicant->id);

            if ($current->isEnrolled()) {
                throw ValidationException::withMessages(['enroll' => 'Pendaftar ini sudah daftar ulang.']);
            }

            if ($current->decision !== Decision::Accepted) {
                throw ValidationException::withMessages(['enroll' => 'Hanya pendaftar yang diterima yang bisa daftar ulang.']);
            }

            try {
                $studentId = $this->students->admit(new NewStudent(
                    name: $current->name,
                    nis: trim($nis),
                    gender: $current->gender,
                    nisn: $current->nisn,
                    birthDate: $current->birth_date,
                    guardianName: $current->guardian_name,
                    guardianPhone: $current->guardian_phone,
                ));
            } catch (StudentAdmissionRefusedException $exception) {
                // One field on the form: the NIS the committee types.
                throw ValidationException::withMessages(['nis' => $exception->getMessage()]);
            }

            $current->forceFill(['student_id' => $studentId, 'enrolled_at' => now()])->save();

            return $studentId;
        });
    }
}
