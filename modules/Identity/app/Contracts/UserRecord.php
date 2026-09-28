<?php

namespace Modules\Identity\App\Contracts;

readonly class UserRecord
{
    /**
     * @param  array<int, string>  $roles
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $emailVerifiedAt,
        public array $roles = [],
    ) {}
}
