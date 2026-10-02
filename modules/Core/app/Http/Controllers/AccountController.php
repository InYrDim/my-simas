<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Actions\CreateStudentAccounts;
use Modules\Core\App\Domain\Actions\CreateTeacherAccount;
use Modules\Core\App\Domain\Actions\LinkTeacherAccount;
use Modules\Core\App\Domain\Actions\ResetLinkedPassword;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;

/**
 * Login accounts of the people in master data: students sign in by NIS,
 * teachers by NIP. The routes already ask for `core.master.manage`; on
 * top of that, creating or linking needs Identity's
 * `identity.users.create` and a reset `identity.users.sendReset`.
 *
 * A teacher's password is random and travels in the session flash
 * (`password`) exactly once, for the admin to pass on.
 */
final class AccountController
{
    public function storeForClass(ClassGroup $classGroup, CreateStudentAccounts $create): RedirectResponse
    {
        Gate::authorize('identity.users.create');

        $result = $create->handle($classGroup->students()->orderBy('name')->get());

        return back()->with('status', $this->summary($result));
    }

    public function storeForStudent(Student $student, CreateStudentAccounts $create): RedirectResponse
    {
        Gate::authorize('identity.users.create');

        $result = $create->handle([$student]);

        if ($result['created'] === 0) {
            return back()->withErrors(['status' => "Akun {$student->name} tidak dibuat: {$result['skipped'][0]['reason']}."]);
        }

        return back()->with('status', "Akun {$student->name} dibuat. Nama pengguna: NIS, kata sandi awal: tanggal lahir (ddmmyyyy).");
    }

    public function resetStudent(Student $student, ResetLinkedPassword $reset): RedirectResponse
    {
        Gate::authorize('identity.users.sendReset');

        $reset->forStudent($student);

        return back()->with('status', "Kata sandi {$student->name} dikembalikan ke tanggal lahir (ddmmyyyy).");
    }

    public function storeForTeacher(Request $request, Teacher $teacher, CreateTeacherAccount $create): RedirectResponse
    {
        Gate::authorize('identity.users.create');

        $validated = $request->validate([
            'role' => ['required', Rule::in(CreateTeacherAccount::ROLES)],
        ]);

        $password = $create->handle($teacher, $validated['role']);

        return back()
            ->with('status', "Akun {$teacher->name} dibuat. Nama pengguna: NIP.")
            ->with('password', $password);
    }

    public function linkTeacher(Teacher $teacher, LinkTeacherAccount $link): RedirectResponse
    {
        Gate::authorize('identity.users.create');

        $link->handle($teacher);

        return back()->with('status', "{$teacher->name} ditautkan ke akun {$teacher->email}.");
    }

    public function resetTeacher(Teacher $teacher, ResetLinkedPassword $reset): RedirectResponse
    {
        Gate::authorize('identity.users.sendReset');

        $password = $reset->forTeacher($teacher);

        return back()
            ->with('status', "Kata sandi {$teacher->name} diganti.")
            ->with('password', $password);
    }

    /**
     * "32 akun dibuat. Dilewati: Andi, Budi (tanpa tanggal lahir); 3 sudah
     * punya akun." Students who already have an account are only counted.
     *
     * @param  array{created: int, skipped: list<array{name: string, reason: string}>}  $result
     */
    private function summary(array $result): string
    {
        $message = "{$result['created']} akun dibuat.";
        $parts = [];

        foreach (collect($result['skipped'])->groupBy('reason') as $reason => $rows) {
            $parts[] = $reason === CreateStudentAccounts::HAS_ACCOUNT
                ? $rows->count().' '.$reason
                : $rows->pluck('name')->implode(', ')." ({$reason})";
        }

        return $parts === [] ? $message : $message.' Dilewati: '.implode('; ', $parts).'.';
    }
}
