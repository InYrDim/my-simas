<?php

namespace Modules\Platform\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Domain\Models\BillingCycle;
use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\InvoiceStatus;

/**
 * Unpaid invoice due in a week. subscription/tenant/plan ids come from
 * the caller.
 *
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $today = Carbon::today();

        return [
            'number' => 'INV-'.$today->format('ym').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'tenant_id' => TenantFactory::new(),
            'subscription_id' => 0,
            'plan_id' => 0,
            'plan_name' => 'Starter',
            'billing_cycle' => BillingCycle::Monthly,
            'amount' => 150_000,
            'status' => InvoiceStatus::Unpaid,
            'issued_at' => $today,
            'due_at' => $today->copy()->addDays(7),
            'period_start' => $today,
            'period_end' => $today->copy()->addMonth(),
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
