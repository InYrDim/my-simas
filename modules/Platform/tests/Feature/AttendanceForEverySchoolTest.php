<?php

use Modules\Platform\App\Contracts\TenantModules;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Infrastructure\Billing\SubscriptionManager;
use Modules\Platform\Database\Factories\PlanFactory;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Database\Seeders\BillingMasterDataSeeder;

/**
 * Absensi is part of every plan for now, so no school loses it when its
 * plan is applied, and schools and plans that existed before the module
 * get it from a one-off migration.
 */
function includeAttendanceForEverySchool(): void
{
    (require base_path('modules/Platform/database/migrations/0008_01_01_000000_include_attendance_for_every_school.php'))->up();
}

it('lists Absensi in every seeded plan', function () {
    $this->seed(BillingMasterDataSeeder::class);

    $plans = Plan::query()->orderBy('sort_order')->get();

    expect($plans)->toHaveCount(3);

    foreach ($plans as $plan) {
        expect($plan->modules)->toContain('core', 'identity', 'attendance');
    }

    // PPDB is the one module that tells the plans apart: only Pro has it.
    expect($plans->pluck('modules', 'key')->map(fn (array $modules): bool => in_array('ppdb', $modules, true))->all())
        ->toBe(['starter' => false, 'standard' => false, 'pro' => true]);
});

it('gives a school on a seeded plan Absensi and keeps it through a plan change', function () {
    $this->seed(BillingMasterDataSeeder::class);
    $tenant = TenantFactory::new()->create();
    $modules = app(TenantModules::class);

    $subscription = app(SubscriptionManager::class)->startTrial($tenant->id, 'starter');

    expect($modules->isEnabled('attendance', $tenant->id))->toBeTrue();

    app(SubscriptionManager::class)->changePlan($subscription, 'pro');

    expect($modules->isEnabled('attendance', $tenant->id))->toBeTrue();
});

it('adds Absensi to the plans and schools that came before it, once', function () {
    $old = PlanFactory::new()->create(['key' => 'lama', 'modules' => ['core', 'identity']]);
    $already = PlanFactory::new()->create(['key' => 'sudah', 'modules' => ['core', 'attendance', 'identity']]);
    $tenant = TenantFactory::new()->create();
    $other = TenantFactory::new()->create();
    $modules = app(TenantModules::class);

    expect($modules->isEnabled('attendance', $tenant->id))->toBeFalse();

    includeAttendanceForEverySchool();
    includeAttendanceForEverySchool();

    expect($old->refresh()->modules)->toBe(['core', 'identity', 'attendance'])
        ->and($already->refresh()->modules)->toBe(['core', 'attendance', 'identity'])
        ->and($modules->isEnabled('attendance', $tenant->id))->toBeTrue()
        ->and($modules->isEnabled('attendance', $other->id))->toBeTrue()
        // Nothing else is switched on along the way.
        ->and($modules->isEnabled('ppdb', $tenant->id))->toBeFalse();
});
