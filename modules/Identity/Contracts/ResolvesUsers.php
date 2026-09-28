<?php

namespace Modules\Identity\Contracts;

interface ResolvesUsers
{
    /**
     * Retrieve a user record by unique email address.
     */
    public function findByEmail(string $email): ?UserRecord;
}
