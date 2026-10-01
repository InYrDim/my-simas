<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Models\SubscriptionStatus;

/**
 * Defaults to a running 14-day trial. tenant_id and plan_id are provided
 * by the caller (->state([...]) or the ->forTenant()/->forPlan() helpers).
 *
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'plan_id' => PlanFactory::new(),
            'billing_cycle' => BillingCycle::Monthly,
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => Carbon::today()->addDays(14),
            'current_period_start' => null,
            'current_period_end' => null,
            'cancelled_at' => null,
        ];
    }

    public function forTenant(string $tenantId): static
    {
        return $this->state(fn (): array => ['tenant_id' => $tenantId]);
    }

    public function forPlan(int $planId): static
    {
        return $this->state(fn (): array => ['plan_id' => $planId]);
    }

    /**
     * Paid and running; the period ends in $daysLeft days (negative =
     * already lapsed).
     */
    public function active(int $daysLeft = 20, BillingCycle $cycle = BillingCycle::Monthly): static
    {
        return $this->state(fn (): array => [
            'billing_cycle' => $cycle,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_start' => Carbon::today()->addDays($daysLeft)->subMonthsNoOverflow($cycle->months()),
            'current_period_end' => Carbon::today()->addDays($daysLeft),
        ]);
    }

    public function trialEndingIn(int $days): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Trial,
            'trial_ends_at' => Carbon::today()->addDays($days),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
