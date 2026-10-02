<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\ApplicantStatus;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\DocumentCheck;

/**
 * Records the committee's check of an applicant: waiting, needing
 * correction (with a note the applicant can read) or verified. Someone
 * who already has a decision stays verified, and an applicant who has
 * re-registered is left alone.
 */
final class VerifyApplicant
{
    public function __construct(
        private readonly DocumentCheck $documents,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Applicant $applicant, ApplicantStatus $status, ?string $note): Applicant
    {
        if ($applicant->isEnrolled()) {
            throw ValidationException::withMessages(['status' => 'Pendaftar yang sudah daftar ulang tidak bisa diverifikasi ulang.']);
        }

        if ($applicant->decision !== Decision::Pending && $status !== ApplicantStatus::Verified) {
            throw ValidationException::withMessages(['status' => 'Pendaftar yang sudah punya keputusan seleksi harus tetap terverifikasi.']);
        }

        $note = $note === null ? null : trim($note);

        if ($status === ApplicantStatus::Revision && ($note === null || $note === '')) {
            throw ValidationException::withMessages(['verification_note' => 'Tulis catatan perbaikan untuk pendaftar.']);
        }

        if ($status === ApplicantStatus::Verified && ! $this->documents->complete($applicant)) {
            throw ValidationException::withMessages(['status' => 'Berkas pendaftar belum lengkap.']);
        }

        $applicant->forceFill([
            'status' => $status,
            'verification_note' => $status === ApplicantStatus::Revision ? $note : null,
        ])->save();

        return $applicant;
    }
}
