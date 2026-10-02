<?php

namespace Modules\Identity\App\Contracts\DTOs;

/**
 * What AccountProvisioner needs to create an account: who it is, how it
 * signs in and which role (machine name from config/roles.php) it gets.
 */
final readonly class NewAccount
{
    public function __construct(
        public string $name,
        public string $username,
        #[\SensitiveParameter] public string $password,
        public string $role,
        public ?string $email = null,
    ) {}
}
