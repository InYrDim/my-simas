<?php

namespace Modules\Platform\App\Http\Support;

use Modules\Platform\App\Domain\Models\Invoice;
use Modules\Platform\App\Domain\Models\Plan;
use Modules\Platform\App\Domain\Models\Subscription;

/**
 * Array shapes the provider console pages receive (camelCase, ISO dates,
 * whole rupiah). One place so every page agrees with types/console.ts.
 */
final class ConsoleResources
{
    /**
     * @return array<string, mixed>
     */
    public static function plan(Plan $plan, ?int $subscribers = null): array
    {
        return [
            'id' => $plan->id,
            'key' => $plan->key,
            'name' => $plan->name,
            'priceMonthly' => $plan->price_monthly,
            'priceYearly' => $plan->price_yearly,
            'maxUsers' => $plan->max_users,
            'modules' => $plan->modules,
            'isActive' => $plan->is_active,
            'sortOrder' => $plan->sort_order,
            'archived' => $plan->archived_at !== null,
            'subscribers' => $subscribers,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function subscription(Subscription $subscription): array
    {
        $plan = $subscription->plan;

        return [
            'id' => $subscription->id,
            'tenantId' => $subscription->tenant_id,
            'tenantName' => $subscription->tenant?->name,
            'tenantSlug' => $subscription->tenant?->slug,
            'planKey' => $plan?->key,
            'planName' => $plan?->name,
            'cycle' => $subscription->billing_cycle->value,
            'status' => $subscription->status->value,
            'state' => $subscription->displayState(),
            'trialEndsAt' => $subscription->trial_ends_at?->toDateString(),
            'periodStart' => $subscription->current_period_start?->toDateString(),
            'periodEnd' => $subscription->current_period_end?->toDateString(),
            'endsAt' => $subscription->endsAt()?->toDateString(),
            'amount' => $plan?->priceFor($subscription->billing_cycle),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invoice(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'tenantId' => $invoice->tenant_id,
            'tenantName' => $invoice->tenant?->name,
            'planName' => $invoice->plan_name,
            'cycle' => $invoice->billing_cycle->value,
            'amount' => $invoice->amount,
            'status' => $invoice->status->value,
            'state' => $invoice->displayState(),
            'issuedAt' => $invoice->issued_at->toDateString(),
            'dueAt' => $invoice->due_at->toDateString(),
            'paidAt' => $invoice->paid_at?->toDateString(),
            'periodStart' => $invoice->period_start->toDateString(),
            'periodEnd' => $invoice->period_end->toDateString(),
        ];
    }
}
