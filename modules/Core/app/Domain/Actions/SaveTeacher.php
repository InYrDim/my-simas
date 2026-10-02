<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\Exceptions\AccountActionRefusedException;
use Modules\Identity\App\Contracts\Exceptions\UsernameTakenException;
use Modules\Identity\App\Contracts\ResolvesUsers;

/**
 * Creates or updates a teacher. An account that signs in by NIP follows a
 * change of the name or the NIP; an account linked by email (it has no
 * username) is left as it is.
 */
final class SaveTeacher
{
    public function __construct(
        private readonly AccountProvisioner $accounts,
        private readonly ResolvesUsers $users,
    ) {}

    /**
     * @param  array{name: string, nip?: string|null, nuptk?: string|null, employment: string, duty: string, email?: string|null}  $data
     *
     * @throws ValidationException when the account cannot follow the change
     */
    public function handle(?Teacher $teacher, array $data): Teacher
    {
        $teacher ??= new Teacher;

        return DB::transaction(function () use ($teacher, $data): Teacher {
            $teacher->fill($data);

            $identityChanged = $teacher->exists && $teacher->isDirty(['name', 'nip']);

            $teacher->save();

            if ($identityChanged) {
                $this->syncAccount($teacher);
            }

            return $teacher;
        });
    }

    private function syncAccount(Teacher $teacher): void
    {
        if ($teacher->user_id === null || $teacher->nip === null || $teacher->nip === '') {
            return;
        }

        $account = $this->users->findMany([$teacher->user_id])[$teacher->user_id] ?? null;

        if ($account === null || $account->username === null) {
            return;
        }

        try {
            $this->accounts->updateIdentity($teacher->user_id, $teacher->name, $teacher->nip);
        } catch (UsernameTakenException) {
            throw ValidationException::withMessages([
                'nip' => 'NIP ini sudah dipakai sebagai nama pengguna akun lain.',
            ]);
        } catch (AccountActionRefusedException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }
    }
}
