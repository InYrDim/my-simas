<?php

namespace Modules\Attendance\Tests\Feature;

use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Student;
use Modules\Identity\App\Domain\Models\User;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Modules\ModuleFlagManager;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;

/*
 * Helpers shared by the Attendance feature tests: a school with the
 * module on, a signed-in member of it, and sample classes and students.
 */

/**
 * A school (module on unless told otherwise) with a signed-in user of the
 * given role.
 */
function attendanceTenant(bool $enabled = true, string $role = 'admin-sekolah', ?string $slug = null): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug ?? ($enabled ? 'absensi-on' : 'absensi-off')]);

    if ($enabled) {
        app(ModuleFlagManager::class)->enable($tenant->id, 'attendance');
    }

    attendanceMember($tenant, $role);

    return $tenant;
}

/**
 * Sign in as a new user of the school holding the role (null = no role).
 */
function attendanceMember(Tenant $tenant, ?string $role): User
{
    $user = UserFactory::new()->forTenant($tenant->id)->create();

    if ($role !== null) {
        attendanceSchool($tenant, fn () => $user->assignTenantRole($role));
    }

    actingAs($user);

    return $user;
}

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function attendanceSchool(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant->id, $callback);
}

/**
 * A class of the school's active academic year (created when missing).
 */
function attendanceClass(Tenant $tenant, string $name = 'X 1'): ClassGroup
{
    return attendanceSchool($tenant, function () use ($name): ClassGroup {
        $year = AcademicYear::query()->first() ?? AcademicYear::factory()->active()->create();
        $grade = Grade::query()->first() ?? Grade::factory()->create(['name' => 'X', 'sort_order' => 1]);

        return ClassGroup::factory()->create([
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            'name' => $name,
        ]);
    });
}

/**
 * An active student of the class.
 *
 * @param  array<string, mixed>  $attributes
 */
function attendanceStudent(Tenant $tenant, ClassGroup $class, string $name, array $attributes = []): Student
{
    return attendanceSchool($tenant, fn (): Student => Student::factory()->create([
        'name' => $name,
        'class_id' => $class->id,
        ...$attributes,
    ]));
}
