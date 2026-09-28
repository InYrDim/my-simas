<?php

namespace Modules\Identity\Services;

use Modules\Identity\Contracts\ResolvesUsers;
use Modules\Identity\Models\User;

class DefaultUserResolver implements ResolvesUsers
{
    /**
     * Retrieve a user by their unique email address.
     */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        return $user;
    }
}
