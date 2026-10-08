<?php

use Database\Seeders\DemoSchoolSeeder;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Support\BillingClock;

/**
 * The demo school starts on a running trial, so the trial banner is what a
 * developer sees on it. The seeder only runs in the local environment.
 */
beforeEach(function () {
    app()->detectEnvironment(fn (): string => 'local');
});

it('seeds the demo school with the school code 123456, on a running trial of the Standard plan', function () {
    $this->artisan('db:seed', ['--class' => DemoSchoolSeeder::class])->assertSuccessful();

    expect(DemoSchoolSeeder::SLUG)->toBe('123456')
        ->and(Tenant::query()->where('slug', 'sekolah-demo')->exists())->toBeFalse();

    $tenant = Tenant::query()->where('slug', DemoSchoolSeeder::SLUG)->firstOrFail();
    $subscription = Subscription::query()->where('tenant_id', $tenant->id)->sole();

    expect($subscription->status)->toBe(SubscriptionStatus::Trial)
        ->and($subscription->isTrial())->toBeTrue()
        ->and($subscription->displayState())->toBe(Subscription::STATE_TRIAL)
        ->and($subscription->trial_ends_at->toDateString())->toBe(BillingClock::today()->addDays((int) config('billing.trial_days', 14))->toDateString())
        ->and($subscription->current_period_end)->toBeNull()
        ->and($subscription->plan_id)->toBe(Plan::query()->where('key', 'standard')->value('id'));
});

it('completes a school that already has the code 123456 instead of creating a second one', function () {
    $existing = Tenant::query()->create(['name' => 'SMA Sekolah Demo', 'slug' => '123456', 'timezone' => 'Asia/Jakarta', 'status' => 'active']);

    $this->artisan('db:seed', ['--class' => DemoSchoolSeeder::class])->assertSuccessful();

    expect(Tenant::query()->where('slug', '123456')->count())->toBe(1)
        ->and(Subscription::query()->where('tenant_id', $existing->id)->count())->toBe(1);
});

it('leaves the subscription alone when it is seeded again', function () {
    $this->artisan('db:seed', ['--class' => DemoSchoolSeeder::class])->assertSuccessful();

    $tenant = Tenant::query()->where('slug', DemoSchoolSeeder::SLUG)->firstOrFail();
    $first = Subscription::query()->where('tenant_id', $tenant->id)->sole();

    $this->artisan('db:seed', ['--class' => DemoSchoolSeeder::class])->assertSuccessful();

    expect(Subscription::query()->where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Subscription::query()->where('tenant_id', $tenant->id)->sole()->id)->toBe($first->id);
});
