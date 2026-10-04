<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Core\App\Domain\Actions\CreateStudentAccounts;
use Modules\Core\App\Domain\Actions\CreateTeacherAccount;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantDirectory;

/**
 * Gives the students, teachers and staff of the dev school `sekolah-a` an
 * account (students: NIS + birth date as ddmmyyyy; teachers and staff: NIP +
 * a random password printed once; every account then gets the fixed STAFF_PASSWORD,
 * TEACHER_PASSWORD or STUDENT_PASSWORD).
 * Development only; people who already have one are skipped.
 *
 *   php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoAccountsSeeder"
 */
class CoreDemoAccountsSeeder extends Seeder
{
    public const SCHOOL_SLUG = 'sekolah-a';

    public const STAFF_PASSWORD = 'demostaf123';

    public const TEACHER_PASSWORD = 'guruku123';

    public const STUDENT_PASSWORD = 'siswaku123';

    public function run(TenantContext $context, TenantDirectory $tenants): void
    {
        $school = collect($tenants->all())->first(fn ($tenant): bool => $tenant->slug === self::SCHOOL_SLUG);

        if ($school === null) {
            $this->command->warn('School ['.self::SCHOOL_SLUG.'] not found.');

            return;
        }

        $context->run($school->id, fn () => $this->seedAccounts());
    }

    /**
     * Seeds the current tenant (call it inside the tenant's context).
     */
    public function seedAccounts(): void
    {
        $students = app(CreateStudentAccounts::class)->handle(Student::query()->orderBy('id')->get());

        $this->command->info("Student accounts created: {$students['created']} (login: NIS, password: birth date ddmmyyyy).");

        foreach ($students['skipped'] as $skipped) {
            if ($skipped['reason'] !== CreateStudentAccounts::HAS_ACCOUNT) {
                $this->command->warn("  skipped {$skipped['name']}: {$skipped['reason']}");
            }
        }

        $rows = [];

        foreach (Teacher::query()->whereNull('user_id')->whereNotNull('nip')->orderBy('id')->get() as $teacher) {
            $role = $teacher->duty === 'Tenaga Kependidikan' ? 'staf-tu' : 'guru';

            $rows[] = [$teacher->name, $role, $teacher->nip, app(CreateTeacherAccount::class)->handle($teacher, $role)];
        }

        if ($rows !== []) {
            $this->command->info('Teacher and staff accounts created (password shown once):');
            $this->command->table(['Name', 'Role', 'NIP (login)', 'Password'], $rows);
        } else {
            $this->command->info('No teacher or staff member without an account and with a NIP.');
        }

        $staff = Teacher::query()->where('duty', 'Tenaga Kependidikan')->whereNotNull('user_id')->pluck('user_id');
        $teachers = Teacher::query()->where('duty', '!=', 'Tenaga Kependidikan')->whereNotNull('user_id')->pluck('user_id');
        $students = Student::query()->whereNotNull('user_id')->pluck('user_id');

        $this->useFixedPassword('Staff', self::STAFF_PASSWORD, $staff);
        $this->useFixedPassword('Teacher', self::TEACHER_PASSWORD, $teachers);
        $this->useFixedPassword('Student', self::STUDENT_PASSWORD, $students);
    }

    /**
     * Demo convenience: the given accounts sign in with a fixed password, also
     * those created by an earlier run.
     *
     * @param  Collection<int, int>  $userIds
     */
    private function useFixedPassword(string $label, string $password, Collection $userIds): void
    {
        // DB::table() bypasses the tenant scope; the ids come from tenant-scoped queries.
        $updated = DB::table('users')->whereIn('id', $userIds)->update([
            'password' => Hash::make($password),
            'must_change_password' => false,
        ]);

        $this->command->info("{$label} accounts: {$updated} (password: {$password}).");
    }
}
