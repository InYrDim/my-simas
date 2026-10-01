<?php

namespace Modules\Identity\App\Domain\Actions;

use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Identity\App\Contracts\UserRecord;
use Modules\Identity\App\Contracts\UserSummary;
use Modules\Identity\App\Domain\Models\User;

class DefaultUserResolver implements ResolvesUsers
{
    /**
     * Retrieve a user record by email within the CURRENT tenant. The
     * BelongsToTenant global scope applies the tenant filter (and
     * fails closed without context).
     */
    public function findByEmail(string $email): ?UserRecord
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        return $user === null ? null : $this->toRecord($user);
    }

    /**
     * Count the CURRENT tenant's accounts. One aggregate query for the
     * three states (an invited account with a null password cannot sign
     * in yet, so it is counted as awaiting, not active), one existence
     * query for the unassigned-role count.
     */
    public function currentTenantSummary(): UserSummary
    {
        $totals = User::query()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN deactivated_at IS NULL AND password IS NOT NULL THEN 1 ELSE 0 END) AS active')
            ->selectRaw('SUM(CASE WHEN deactivated_at IS NULL AND password IS NULL THEN 1 ELSE 0 END) AS awaiting_activation')
            ->selectRaw('SUM(CASE WHEN deactivated_at IS NOT NULL THEN 1 ELSE 0 END) AS deactivated')
            ->toBase()
            ->first();

        return new UserSummary(
            total: (int) ($totals->total ?? 0),
            active: (int) ($totals->active ?? 0),
            awaitingActivation: (int) ($totals->awaiting_activation ?? 0),
            deactivated: (int) ($totals->deactivated ?? 0),
            withoutRole: User::query()->whereDoesntHave('roles')->count(),
        );
    }

    /**
     * Map the internal model to the public DTO.
     */
    private function toRecord(User $user): UserRecord
    {
        return new UserRecord(
            id: $user->id,
            tenantId: $user->tenant_id,
            name: $user->name,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            roles: $user->tenantRoleNames(),
        );
    }
}
