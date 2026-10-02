<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\ApplicantChecks;

/**
 * Changes an applicant's own data. The path is locked once there is a
 * decision (the ranking and the quota depend on it), and an applicant who
 * has re-registered is a student now — their data is no longer changed
 * here.
 */
final class UpdateApplicant
{
    public function __construct(
        private readonly ApplicantChecks $checks,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function handle(Applicant $applicant, array $data): Applicant
    {
        if ($applicant->isEnrolled()) {
            throw ValidationException::withMessages(['applicant' => 'Pendaftar yang sudah daftar ulang tidak bisa diubah.']);
        }

        $data = Arr::only($data, Applicant::DATA_FIELDS);

        if ($applicant->decision !== Decision::Pending && (int) $data['path_id'] !== $applicant->path_id) {
            throw ValidationException::withMessages(['path_id' => 'Jalur tidak bisa diubah setelah ada keputusan seleksi.']);
        }

        $period = AdmissionPeriod::query()->findOrFail($applicant->period_id);

        $this->checks->choices($period, $data, $applicant);

        $applicant->fill($data)->save();

        return $applicant;
    }
}
