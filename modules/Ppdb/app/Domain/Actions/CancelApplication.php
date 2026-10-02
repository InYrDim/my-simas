<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Ppdb\App\Domain\Enums\Decision;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;

/**
 * Removes an applicant's registration (a wrong school, a duplicate, a
 * mistake) — only while nothing has been decided about them and they have
 * not re-registered. Their registration number is not reused, and an
 * account that registered online is freed to join a school again.
 */
final class CancelApplication
{
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

        DB::transaction(function () use ($applicant): void {
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
