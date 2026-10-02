<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\App\Domain\Models\AcademicYear;
use Modules\Core\App\Domain\Models\ClassGroup;
use Modules\Core\App\Domain\Models\Grade;
use Modules\Core\App\Domain\Models\Semester;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\Database\Factories\TenantFactory;

use function Pest\Laravel\actingAs;

/*
 * Helpers shared by the Core feature tests: a signed-in school user,
 * running code inside a school, and sample academic years and classes.
 */

function schoolAs(string $slug, string $role = 'admin-sekolah'): Tenant
{
    $tenant = TenantFactory::new()->create(['slug' => $slug]);
    $user = UserFactory::new()->forTenant($tenant->id)->create(['email' => "{$role}@{$slug}.test"]);

    app(TenantContext::class)->run($tenant->id, fn () => $user->assignTenantRole($role));

    actingAs($user);

    return $tenant;
}

/**
 * @template T
 *
 * @param  callable(): T  $callback
 * @return T
 */
function inSchool(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant->id, $callback);
}

function yearWithSemesters(Tenant $tenant, string $status = 'draft', string $start = '2026-07-13'): AcademicYear
{
    return inSchool($tenant, function () use ($status, $start): AcademicYear {
        $startYear = (int) substr($start, 0, 4);
        $year = AcademicYear::factory()->create([
            'name' => $startYear.'/'.($startYear + 1),
            'start_date' => $start,
            'end_date' => ($startYear + 1).'-06-26',
            'status' => AcademicYearStatus::from($status),
        ]);
        Semester::factory()->create(['academic_year_id' => $year->id, 'name' => 'Ganjil', 'start_date' => $start, 'end_date' => $startYear.'-12-19']);
        Semester::factory()->create(['academic_year_id' => $year->id, 'name' => 'Genap', 'start_date' => ($startYear + 1).'-01-04', 'end_date' => ($startYear + 1).'-06-26']);

        return $year;
    });
}

/**
 * A class in the school's first academic year (an active one is created
 * when there is none) and first grade.
 *
 * @param  array<string, mixed>  $attributes
 */
function classIn(Tenant $tenant, array $attributes = []): ClassGroup
{
    return inSchool($tenant, function () use ($attributes): ClassGroup {
        $year = AcademicYear::query()->first() ?? AcademicYear::factory()->active()->create();
        $grade = Grade::query()->first() ?? Grade::factory()->create(['name' => 'X', 'sort_order' => 1]);

        return ClassGroup::factory()->create([
            'academic_year_id' => $year->id,
            'grade_id' => $grade->id,
            ...$attributes,
        ]);
    });
}

/**
 * End the signed-in session inside a test, so the next request can sign in
 * through the login form (a guest route).
 */
function signOutOfSchool(): void
{
    auth()->guard('web')->logout();
    app('auth')->forgetGuards();
}
