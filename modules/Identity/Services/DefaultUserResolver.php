<?php

namespace Modules\Identity\Services;

use Modules\Identity\Contracts\ResolvesUsers;
use Modules\Identity\Contracts\UserRecord;
use Modules\Identity\Models\User;

class DefaultUserResolver implements ResolvesUsers
{
    /**
     * Retrieve a user record by unique email address.
     */
    public function findByEmail(string $email): ?UserRecord
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        return $user === null ? null : $this->toRecord($user);
    }

    /**
     * Map the internal model to the public DTO.
     */
    private function toRecord(User $user): UserRecord
    {
        return new UserRecord(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            emailVerifiedAt: $user->email_verified_at?->toIso8601String(),
            roles: [],
        );
    }
}
