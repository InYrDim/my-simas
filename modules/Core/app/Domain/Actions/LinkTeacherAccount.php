<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Identity\App\Contracts\ResolvesUsers;

/**
 * Links a teacher to the account that already exists under the same
 * email (an invited teacher, a school admin who also teaches). Nothing
 * about the account changes; the teacher record only learns its id.
 */
final class LinkTeacherAccount
{
    public function __construct(private readonly ResolvesUsers $users) {}

    /**
     * @throws ValidationException
     */
    public function handle(Teacher $teacher): void
    {
        if ($teacher->user_id !== null) {
            throw ValidationException::withMessages(['status' => "{$teacher->name} sudah punya akun."]);
        }

        if ($teacher->email === null || $teacher->email === '') {
            throw ValidationException::withMessages([
                'status' => "Isi email {$teacher->name} lebih dulu; akun dicari berdasarkan email itu.",
            ]);
        }

        $account = $this->users->findByEmail(mb_strtolower($teacher->email));

        if ($account === null) {
            throw ValidationException::withMessages([
                'status' => "Belum ada akun dengan email {$teacher->email}. Undang lewat halaman Pengguna lebih dulu.",
            ]);
        }

        $other = Teacher::query()->where('user_id', $account->id)->first();

        if ($other !== null) {
            throw ValidationException::withMessages([
                'status' => "Akun {$teacher->email} sudah tertaut ke {$other->name}.",
            ]);
        }

        $teacher->forceFill(['user_id' => $account->id])->save();
    }
}
