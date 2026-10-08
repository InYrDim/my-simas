<?php

namespace Modules\Platform\App\Http\Support;

use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Subscription;
use Modules\Platform\App\Domain\Support\BillingClock;

/**
 * The shared Inertia prop `trial`: what the banner at the top of every
 * school page needs while the school is still on its trial (or in the
 * grace period just after it). Null for everyone else, so no page pays for
 * more than one small query.
 *
 * It is sent to every signed-in user of the school, not only to those who
 * may see billing: that a trial is running is not secret, and a teacher
 * should know why the school may lose access. The amounts, the plan and the
 * invoices stay behind `platform.billing.view`.
 */
final class TrialNoticePayload
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{state: string, endsOn: string, daysLeft: int, accessEndsOn: string|null}|null
     */
    public function build(): ?array
    {
        $tenantId = $this->context->id();

        if ($tenantId === null) {
            return null;
        }

        $subscription = Subscription::query()->with('tenant')->where('tenant_id', $tenantId)->first();

        if ($subscription === null || $subscription->tenant?->billing_exempt === true) {
            return null;
        }

        $state = $subscription->displayState();
        $end = $subscription->trial_ends_at;

        if ($end === null || ! in_array($state, [Subscription::STATE_TRIAL, Subscription::STATE_TRIAL_EXPIRED], true)) {
            return null;
        }

        return [
            'state' => $state,
            'endsOn' => $end->toDateString(),
            'daysLeft' => (int) BillingClock::today()->diffInDays($end, false),
            'accessEndsOn' => $subscription->accessEndsAt()?->toDateString(),
        ];
    }
}
