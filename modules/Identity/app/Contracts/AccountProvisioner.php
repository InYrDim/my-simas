<?php

namespace Modules\Identity\App\Contracts;

use Modules\Identity\App\Contracts\DTOs\NewAccount;
use Modules\Identity\App\Contracts\Exceptions\AccountActionRefusedException;
use Modules\Identity\App\Contracts\Exceptions\UsernameTakenException;

/**
 * Lets the module that owns a person's record (a student, a teacher) give
 * that person an account and keep it in step, without touching the User
 * model. Every call works on the CURRENT tenant and fails closed without
 * one. The caller stores the returned id as a plain `user_id` column.
 *
 * An account made or reset here carries a password its owner did not
 * choose, so it is flagged to be changed at the next login.
 */
interface AccountProvisioner
{
    /**
     * Create an account that signs in by username.
     *
     * @return int the id of the new account
     *
     * @throws UsernameTakenException when the username or the email is in use in this school
     * @throws AccountActionRefusedException when the role does not exist
     */
    public function create(NewAccount $account): int;

    /**
     * Replace the password; the owner has to change it at the next login.
     *
     * @throws AccountActionRefusedException when the account does not exist
     */
    public function resetPassword(int $userId, #[\SensitiveParameter] string $password): void;

    /**
     * Follow a change of the person's name or number (NIS, NIP).
     *
     * @throws UsernameTakenException when another account holds the username
     * @throws AccountActionRefusedException when the account does not exist
     */
    public function updateIdentity(int $userId, string $name, string $username): void;

    /**
     * Refuse further logins; the account and its roles are kept.
     *
     * @throws AccountActionRefusedException for the school's last active admin, or an unknown account
     */
    public function deactivate(int $userId): void;

    /**
     * @throws AccountActionRefusedException when the account does not exist
     */
    public function reactivate(int $userId): void;
}
