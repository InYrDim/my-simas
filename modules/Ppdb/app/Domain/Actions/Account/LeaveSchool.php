<?php

namespace Modules\Ppdb\App\Domain\Actions\Account;

use Illuminate\Validation\ValidationException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;

/**
 * Takes an account out of the school it joined — only while it has not
 * sent the registration form. After that the applicant is locked to the
 * school; a wrong choice is sorted out by the school's committee, which
 * can cancel the registration.
 */
final class LeaveSchool
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(PpdbAccount $account): void
    {
        if ($account->tenant_id === null) {
            return;
        }

        $applied = $this->context->run(
            $account->tenant_id,
            fn (): bool => Applicant::query()->where('account_id', $account->id)->exists(),
        );

        if ($applied) {
            throw ValidationException::withMessages([
                'school' => 'Anda sudah mengirim formulir pendaftaran, jadi tidak bisa pindah sekolah sendiri. Hubungi panitia PPDB sekolah.',
            ]);
        }

        $account->forceFill(['tenant_id' => null])->save();
    }
}
