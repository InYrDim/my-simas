<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\Exceptions\AccountActionRefusedException;

/**
 * Resets the password of the account linked to a student or a teacher,
 * for accounts that have no email to send a reset link to. The owner has
 * to change it again at the next login.
 */
final class ResetLinkedPassword
{
    public function __construct(private readonly AccountProvisioner $accounts) {}

    /**
     * Back to the first password: the birth date (ddmmyyyy).
     *
     * @throws ValidationException when the student has no account or no birth date
     */
    public function forStudent(Student $student): void
    {
        $password = CreateStudentAccounts::initialPassword($student);

        if ($student->user_id === null || $password === null) {
            throw ValidationException::withMessages([
                'status' => "{$student->name} belum punya akun atau tanggal lahirnya kosong.",
            ]);
        }

        $this->reset($student->user_id, $password);
    }

    /**
     * @return string the new password, to be shown to the admin once
     *
     * @throws ValidationException when the teacher has no account
     */
    public function forTeacher(Teacher $teacher): string
    {
        if ($teacher->user_id === null) {
            throw ValidationException::withMessages(['status' => "{$teacher->name} belum punya akun."]);
        }

        $password = self::randomPassword();

        $this->reset($teacher->user_id, $password);

        return $password;
    }

    /**
     * Ten characters that are easy to read aloud and type: no 0/O, 1/l/I.
     */
    public static function randomPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

        return implode('', array_map(
            fn (): string => $alphabet[random_int(0, strlen($alphabet) - 1)],
            range(1, 10),
        ));
    }

    private function reset(int $userId, #[\SensitiveParameter] string $password): void
    {
        try {
            $this->accounts->resetPassword($userId, $password);
        } catch (AccountActionRefusedException $exception) {
            throw ValidationException::withMessages(['status' => $exception->getMessage()]);
        }
    }
}
