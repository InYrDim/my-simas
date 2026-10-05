<?php

namespace Modules\Attendance\Tests\Feature;

use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\PeriodSlot;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Subject;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Models\TeachingAssignment;
use Modules\Core\App\Domain\Models\TimetableEntry;
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

/**
 * A lesson slot of the bell schedule; Friday 07:15–08:00 by default.
 *
 * @param  array<string, mixed>  $attributes
 */
function attendanceSlot(Tenant $tenant, array $attributes = []): PeriodSlot
{
    return attendanceSchool($tenant, fn (): PeriodSlot => PeriodSlot::factory()->create([
        'day' => 5,
        'start_time' => '07:15:00',
        'end_time' => '08:00:00',
        ...$attributes,
    ]));
}

/**
 * A subject taught in the class by a teacher and put in the slot: the
 * subject, the teacher behind `$userId` (when given), the class teaching
 * assignment and the timetable entry.
 */
function attendanceTeach(Tenant $tenant, ClassGroup $class, PeriodSlot $slot, string $subjectName = 'Matematika', ?int $userId = null): Subject
{
    return attendanceSchool($tenant, function () use ($class, $slot, $subjectName, $userId): Subject {
        $subject = Subject::factory()->create(['name' => $subjectName]);
        // One teacher record per account: a second lesson of the same
        // account belongs to the same timetable.
        $teacher = $userId === null ? null : Teacher::query()->where('user_id', $userId)->first();
        $teacher ??= Teacher::factory()->create(['name' => "Guru {$subjectName}"]);

        if ($userId !== null && $teacher->user_id === null) {
            $teacher->forceFill(['user_id' => $userId])->save();
        }

        TeachingAssignment::factory()->create(['class_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id]);
        TimetableEntry::factory()->create(['period_slot_id' => $slot->id, 'class_id' => $class->id, 'subject_id' => $subject->id]);

        return $subject;
    });
}

/**
 * The signed-in teacher's own lesson: a class of the active year with a
 * subject taught and scheduled in a Friday slot.
 *
 * @param  array<string, mixed>  $slotAttributes
 * @return array{0: ClassGroup, 1: PeriodSlot, 2: Subject}
 */
function attendanceOwnLesson(Tenant $tenant, array $slotAttributes = [], string $subjectName = 'Matematika'): array
{
    $slot = attendanceSlot($tenant, $slotAttributes);
    $class = attendanceClass($tenant);
    $subject = attendanceTeach($tenant, $class, $slot, $subjectName, auth()->id());

    return [$class, $slot, $subject];
}
