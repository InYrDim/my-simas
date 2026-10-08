<?php

namespace Modules\Core\Tests\Feature;

use Modules\Core\App\Domain\Models\Student;
use Modules\Platform\App\Contracts\TenantUsage;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Database\Factories\TenantFactory;

require_once __DIR__.'/Support/helpers.php';

/**
 * The `students` usage meter: every student row counts, whatever its status.
 */
function studentUsageOf(string $tenantId): object
{
    return collect(app(TenantUsage::class)->forTenant($tenantId))->firstWhere('key', 'students');
}

it('counts every student whatever its status', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'usage-all']);

    inSchool($tenant, function (): void {
        foreach (['active', 'graduated', 'transferred', 'left'] as $status) {
            Student::factory()->status($status)->create();
        }
    });

    expect(studentUsageOf($tenant->id)->used)->toBe(4);
});

it('never counts the students of another school', function () {
    $a = TenantFactory::new()->create(['slug' => 'usage-a']);
    $b = TenantFactory::new()->create(['slug' => 'usage-b']);

    inSchool($a, fn () => Student::factory()->count(2)->create());
    inSchool($b, fn () => Student::factory()->count(5)->create());

    expect(studentUsageOf($a->id)->used)->toBe(2)
        ->and(studentUsageOf($b->id)->used)->toBe(5);
});

it('sets the count against the student limit of the plan', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'usage-limit']);
    SubscriptionFactory::new()->forTenant($tenant->id)
        ->forPlan(PlanFactory::new()->withLimits(['students' => 3])->create()->id)
        ->create();

    inSchool($tenant, fn () => Student::factory()->count(4)->create());

    $line = studentUsageOf($tenant->id);

    expect($line->limit)->toBe(3)
        ->and($line->state)->toBe('over');
});
