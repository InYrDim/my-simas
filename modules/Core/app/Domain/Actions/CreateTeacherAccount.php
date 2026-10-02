<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\DTOs\NewAccount;
use Modules\Identity\App\Contracts\Exceptions\UsernameTakenException;

/**
 * Gives a teacher or staff member an account that signs in by NIP. A
 * teacher record holds no birth date, so the first password is random:
 * it is returned once for the admin to pass on and has to be changed at
 * the first login. A teacher without a NIP is invited by email from the
 * user list and linked afterwards (LinkTeacherAccount).
 */
final class CreateTeacherAccount
{
    public const ROLES = ['guru', 'staf-tu'];

    public function __construct(private readonly AccountProvisioner $accounts) {}

    /**
     * @return string the first password, to be shown to the admin once
     *
     * @throws ValidationException
     */
    public function handle(Teacher $teacher, string $role): string
    {
        if ($teacher->user_id !== null) {
            throw ValidationException::withMessages(['status' => "{$teacher->name} sudah punya akun."]);
        }

        if ($teacher->nip === null || $teacher->nip === '') {
            throw ValidationException::withMessages([
                'status' => "{$teacher->name} belum punya NIP. Undang lewat email di halaman Pengguna, lalu tautkan akunnya di sini.",
            ]);
        }

        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages(['role' => 'Peran tidak dikenal.']);
        }

        $password = ResetLinkedPassword::randomPassword();

        try {
            DB::transaction(function () use ($teacher, $role, $password): void {
                $userId = $this->accounts->create(new NewAccount(
                    name: $teacher->name,
                    username: (string) $teacher->nip,
                    password: $password,
                    role: $role,
                    email: $teacher->email,
                ));

                $teacher->forceFill(['user_id' => $userId])->save();
            });
        } catch (UsernameTakenException) {
            throw ValidationException::withMessages([
                'status' => "NIP atau email {$teacher->name} sudah dipakai akun lain. Bila itu akunnya sendiri, tautkan akun tersebut.",
            ]);
        }

        return $password;
    }
}
