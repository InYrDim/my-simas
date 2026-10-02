<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Domain\Support\AnswerFiles;

/**
 * Removes an applicant's registration (a wrong school, a duplicate, a
 * mistake) — only while nothing has been decided about them and they have
 * not re-registered. Their registration number is not reused, and an
 * account that registered online is freed to join a school again.
 */
final class CancelApplication
{
    public function __construct(
        private readonly AnswerFiles $files,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(Applicant $applicant): void
    {
        if ($applicant->decision !== Decision::Pending || $applicant->isEnrolled()) {
            throw ValidationException::withMessages([
                'applicant' => 'Pendaftaran yang sudah punya keputusan seleksi tidak bisa dibatalkan.',
            ]);
        }

        $files = $this->files->pathsOf($applicant);

        DB::transaction(function () use ($applicant, $files): void {
            ApplicantAnswer::query()->where('applicant_id', $applicant->id)->delete();
            DB::afterCommit(function () use ($files): void {
                foreach ($files as $path) {
                    $this->files->delete($path);
                }
            });
            $applicant->delete();

            // The applicant's account is free to join a school again. The
            // account is central; the school check keeps this to an account
            // that is still joined to THIS school.
            if ($applicant->account_id !== null) {
                PpdbAccount::query()
                    ->whereKey($applicant->account_id)
                    ->where('tenant_id', $applicant->tenant_id)
                    ->update(['tenant_id' => null]);
            }
        });
    }
}
