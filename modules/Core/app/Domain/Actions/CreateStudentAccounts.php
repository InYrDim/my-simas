<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\App\Domain\Models\Student;
use Modules\Identity\App\Contracts\AccountProvisioner;
use Modules\Identity\App\Contracts\DTOs\NewAccount;
use Modules\Identity\App\Contracts\Exceptions\UsernameTakenException;

/**
 * Gives students an account: the NIS is the username and the birth date
 * (ddmmyyyy) the first password, which the student has to change at the
 * first login. A student who cannot get one is skipped with the reason,
 * so running it again for a class only fills the gaps.
 */
final class CreateStudentAccounts
{
    public const ROLE = 'siswa';

    public const HAS_ACCOUNT = 'sudah punya akun';

    public const NOT_ACTIVE = 'bukan siswa aktif';

    public const NO_BIRTH_DATE = 'tanpa tanggal lahir';

    public const USERNAME_TAKEN = 'NIS sudah dipakai akun lain';

    public function __construct(private readonly AccountProvisioner $accounts) {}

    /**
     * @param  iterable<Student>  $students
     * @return array{created: int, skipped: list<array{name: string, reason: string}>}
     */
    public function handle(iterable $students): array
    {
        $created = 0;
        $skipped = [];

        foreach ($students as $student) {
            $reason = $this->create($student);

            if ($reason === null) {
                $created++;
            } else {
                $skipped[] = ['name' => $student->name, 'reason' => $reason];
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * The password a student's account starts with, or null for a student
     * without a birth date.
     */
    public static function initialPassword(Student $student): ?string
    {
        return $student->birth_date?->format('dmY');
    }

    /**
     * @return string|null why the student was skipped; null when the account was created
     */
    private function create(Student $student): ?string
    {
        if ($student->user_id !== null) {
            return self::HAS_ACCOUNT;
        }

        if (! $student->isActive()) {
            return self::NOT_ACTIVE;
        }

        $password = self::initialPassword($student);

        if ($password === null) {
            return self::NO_BIRTH_DATE;
        }

        try {
            DB::transaction(function () use ($student, $password): void {
                $userId = $this->accounts->create(new NewAccount(
                    name: $student->name,
                    username: $student->nis,
                    password: $password,
                    role: self::ROLE,
                ));

                $student->forceFill(['user_id' => $userId])->save();
            });
        } catch (UsernameTakenException) {
            return self::USERNAME_TAKEN;
        }

        return null;
    }
}
