<?php

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\App\Domain\Actions\CreateStudentAccounts;
use Modules\Core\App\Domain\Actions\CreateTeacherAccount;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantDirectory;

/**
 * Gives the students, teachers and staff of the dev school `sekolah-a` an
 * account (students: NIS + birth date as ddmmyyyy; teachers and staff: NIP +
 * a random password printed once). Development only; people who already
 * have one are skipped.
 *
 *   php artisan db:seed --class="Modules\Core\Database\Seeders\CoreDemoAccountsSeeder"
 */
class CoreDemoAccountsSeeder extends Seeder
{
    public const SCHOOL_SLUG = 'sekolah-a';

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
    }
}
