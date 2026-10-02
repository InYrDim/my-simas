<?php

namespace Modules\Identity\App\Infrastructure\Accounts;

use Illuminate\Support\Facades\DB;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\DTOs\NewAccount;
use Modules\Identity\App\Contracts\Exceptions\AccountActionRefusedException;
use Modules\Identity\App\Contracts\Exceptions\UsernameTakenException;
use Modules\Identity\App\Domain\Actions\DeactivateUser;
use Modules\Identity\App\Domain\Actions\ReactivateUser;
use Modules\Identity\App\Domain\Exceptions\DeactivationNotAllowedException;
use Modules\Identity\App\Domain\Models\User;

/**
 * AccountProvisioner over the User model. Every query goes through the
 * tenant scope, so an id or a username of another school is never found
 * and a call without a tenant context throws.
 */
final class DefaultAccountProvisioner implements AccountProvisioner
{
    public function __construct(
        private readonly DeactivateUser $deactivate,
        private readonly ReactivateUser $reactivate,
    ) {}

    public function create(NewAccount $account): int
    {
        if (! array_key_exists($account->role, (array) config('roles', []))) {
            throw new AccountActionRefusedException("Peran [{$account->role}] tidak dikenal.");
        }

        $email = $account->email === null ? null : mb_strtolower(trim($account->email));

        $this->assertFree($account->username, $email);

        return DB::transaction(function () use ($account, $email): int {
            $user = User::query()->create([
                'name' => $account->name,
                'username' => $account->username,
                'email' => $email,
                'password' => $account->password,
            ]);

            $user->forceFill(['must_change_password' => true])->save();
            $user->assignTenantRole($account->role);

            return (int) $user->id;
        });
    }

    public function resetPassword(int $userId, #[\SensitiveParameter] string $password): void
    {
        $this->find($userId)->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();
    }

    public function updateIdentity(int $userId, string $name, string $username): void
    {
        $user = $this->find($userId);

        if ($user->username !== $username) {
            $this->assertFree($username, null);
        }

        $user->forceFill(['name' => $name, 'username' => $username])->save();
    }

    public function deactivate(int $userId): void
    {
        $user = $this->find($userId);

        if (! $user->isActive()) {
            return;
        }

        try {
            $this->deactivate->handleAsProvider($user);
        } catch (DeactivationNotAllowedException) {
            throw new AccountActionRefusedException('Admin sekolah aktif terakhir tidak bisa dinonaktifkan.');
        }
    }

    public function reactivate(int $userId): void
    {
        $this->reactivate->handle($this->find($userId));
    }

    private function find(int $userId): User
    {
        return User::query()->find($userId)
            ?? throw new AccountActionRefusedException('Akun tidak ditemukan di sekolah ini.');
    }

    private function assertFree(string $username, ?string $email): void
    {
        $taken = User::query()
            ->where(fn ($query) => $query
                ->where('username', $username)
                ->when($email !== null, fn ($inner) => $inner->orWhere('email', $email)))
            ->exists();

        if ($taken) {
            throw new UsernameTakenException("Nama pengguna [{$username}] atau emailnya sudah dipakai akun lain.");
        }
    }
}
