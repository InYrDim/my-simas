<?php

namespace Modules\Ppdb\App\Domain\Actions\Account;

use Modules\Ppdb\App\Domain\Models\PpdbAccount;
use Modules\Ppdb\App\Infrastructure\Account\AccountLinks;

/**
 * Creates an applicant's account and mails the link that verifies the
 * email. The account has not joined a school yet.
 */
final class RegisterAccount
{
    public function __construct(
        private readonly AccountLinks $links,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function handle(array $data): PpdbAccount
    {
        $account = PpdbAccount::query()->create([
            'name' => trim($data['name']),
            'email' => mb_strtolower(trim($data['email'])),
            'password' => $data['password'],
        ]);

        $this->links->sendVerification($account);

        return $account;
    }
}
