<?php

namespace Modules\Ppdb\App\Domain\Actions\Account;

use Illuminate\Validation\ValidationException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantDirectory;
use Modules\Platform\App\Contracts\TenantModules;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\PpdbAccount;

/**
 * Joins an applicant's account to one school by the school's code (the
 * code the school's own people type when they sign in).
 *
 * Every reason the school cannot be joined — no such code, a school that
 * is suspended, one without PPDB, one with no running period — is refused
 * with the SAME message, so the form cannot be used to find out which
 * schools exist or use PPDB. An account joins one school at a time: it
 * must leave the one it is in first.
 */
final class JoinSchool
{
    public const NOT_AVAILABLE = 'Kode sekolah tidak dikenali atau PPDB sekolah itu belum dibuka.';

    public function __construct(
        private readonly TenantDirectory $tenants,
        private readonly TenantModules $modules,
        private readonly TenantContext $context,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(PpdbAccount $account, string $code): void
    {
        $code = mb_strtolower(trim($code));
        $tenantId = $this->resolve($code);

        if ($account->tenant_id === $tenantId) {
            return;
        }

        if ($account->tenant_id !== null) {
            throw ValidationException::withMessages(['code' => 'Anda sudah bergabung ke sebuah sekolah. Keluar dari sekolah itu lebih dulu bila ingin pindah.']);
        }

        $account->forceFill(['tenant_id' => $tenantId])->save();
    }

    /**
     * The id of the school this code names, when it can be joined.
     */
    private function resolve(string $code): string
    {
        // A school code is a 26-character id; anything else is not worth a query.
        if (strlen($code) !== 26 || ! ctype_alnum($code)) {
            throw $this->refused();
        }

        $tenant = $this->tenants->findMany([$code])[$code] ?? null;

        if ($tenant === null
            || $tenant->status->value !== 'active'
            || ! $this->modules->isEnabled('ppdb', $tenant->id)
            || ! $this->context->run($tenant->id, fn (): bool => AdmissionPeriod::active() !== null)) {
            throw $this->refused();
        }

        return $tenant->id;
    }

    private function refused(): ValidationException
    {
        return ValidationException::withMessages(['code' => self::NOT_AVAILABLE]);
    }
}
