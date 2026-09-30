<?php

namespace Modules\Identity\App\Domain\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;
use Modules\Identity\App\Domain\Models\User;

/**
 * School-admin user management gate (Fase 2 Stage 9).
 *
 * Permission = which action; the policy = which data (a target user
 * must belong to the SAME tenant as the actor — re-asserted here even
 * though every lookup is already tenant-scoped, so a policy bypass can
 * never become a cross-tenant write).
 *
 * Permissions come from the actor's tenant roles via Platform's
 * HasTenantRoles wrapper (Spatie is never imported here). Users without
 * the identity.users.* permissions — Guru, Staf/TU — are refused.
 */
class UserPolicy
{
    use HandlesAuthorization;

    public function before(User $actor, string $ability): ?Response
    {
        // Deactivated accounts hold no rights anywhere (their sessions
        // are already ended by middleware; this closes CLI/queue edges).
        if (! $actor->isActive()) {
            return Response::deny();
        }

        return null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->can('identity.users.view');
    }

    /**
     * Invitation gating: direct-create AND email invitations (a
     * password-null account + SetPasswordMail) both count as creating
     * a user — one permission, no separate invite permission.
     */
    public function create(User $actor): bool
    {
        return $actor->can('identity.users.create');
    }

    /**
     * Profile edits AND role assign/remove (permission identity.users.update).
     */
    public function update(User $actor, User $target): bool
    {
        return $this->sameTenant($actor, $target)
            && $actor->can('identity.users.update');
    }

    public function deactivate(User $actor, User $target): bool
    {
        return $this->sameTenant($actor, $target)
            && $actor->can('identity.users.deactivate');
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $this->sameTenant($actor, $target)
            && $actor->can('identity.users.deactivate');
    }

    /**
     * Reuse the Stage 4 machinery: sends through the tenant-scoped
     * broker (User::sendPasswordResetNotification). A user without a
     * password is not a reset candidate (send is skipped silently) —
     * re-inviting them is the Stage 10 path.
     */
    public function sendReset(User $actor, User $target): bool
    {
        return $this->sameTenant($actor, $target)
            && $actor->can('identity.users.sendReset');
    }

    /**
     * Re-assert the tenancy of the TARGET — the policy is the last
     * line of defence against a cross-tenant write even if some
     * lookup someday returns an out-of-tenant model.
     */
    private function sameTenant(User $actor, User $target): bool
    {
        return $actor->tenant_id === $target->tenant_id;
    }
}
