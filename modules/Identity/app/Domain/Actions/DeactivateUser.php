<?php

namespace Modules\Identity\App\Domain\Actions;

use Modules\Identity\App\Domain\Exceptions\DeactivationNotAllowedException;
use Modules\Identity\App\Domain\Models\User;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Deactivate a user inside the CURRENT tenant (audit stamp, no
 * deletion — roles are kept so reactivation restores access).
 *
 * Anti-lockout invariants (Fase 2 Stage 3):
 *  1. No self-deactivation — an admin cannot lock themselves out
 *     mid-action.
 *  2. The tenant's LAST active school admin cannot be deactivated —
 *     a school must never end up with nobody able to manage it.
 *
 * The role check runs under the TARGET's own tenant context (explicit
 * run(), not the ambient one): Spatie's team pointer is only valid
 * while a context is set, and this action must be correct from HTTP
 * requests AND from CLI/tests where no ambient context exists.
 */
final class DeactivateUser
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw new DeactivationNotAllowedException('You cannot deactivate your own account.');
        }

        $this->context->run($target->tenant_id, function () use ($target): void {
            if ($this->isLastActiveAdmin($target)) {
                throw new DeactivationNotAllowedException(
                    'The last active school admin cannot be deactivated.',
                );
            }

            $target->forceFill(['deactivated_at' => now()])->save();
        });
    }

    /**
     * Whether the target is the tenant's only ACTIVE admin-sekolah.
     * Deactivated admins do not count as guardians (they cannot act).
     */
    private function isLastActiveAdmin(User $target): bool
    {
        if (! $target->hasTenantRole('admin-sekolah')) {
            return false;
        }

        $activeAdmins = User::query()
            ->where('deactivated_at', null)
            ->whereHas('roles', fn ($query) => $query->where('name', 'admin-sekolah'))
            ->count();

        return $activeAdmins <= 1;
    }
}
