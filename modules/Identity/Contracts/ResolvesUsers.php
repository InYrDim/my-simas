<?php

namespace Modules\Identity\Contracts;

use Modules\Identity\Models\User;

interface ResolvesUsers
{
    /**
     * Retrieve a user by their unique email address.
     */
    public function findByEmail(string $email): ?User;
}
