<?php

namespace Modules\Platform\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\Database\Factories\SubscriptionFactory;

/**
 * A tenant's subscription (one per tenant). Trial and paid periods are
 * both stored as dates; due/overdue/trial-expired are derived, never
 * written by a job. Internal to Platform.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $plan_id
 * @property BillingCycle $billing_cycle
 * @property SubscriptionStatus $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $cancelled_at
 * @property int|null $scheduled_plan_id a downgrade that takes effect at the end of the paid period
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Plan|null $plan
 * @property-read Tenant|null $tenant
 */
#[UseFactory(SubscriptionFactory::class)]
#[Fillable(['tenant_id', 'plan_id', 'billing_cycle', 'status', 'trial_ends_at', 'current_period_start', 'current_period_end', 'cancelled_at', 'scheduled_plan_id'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /** Derived display states (what the console shows). */
    public const STATE_TRIAL = 'trial';

    public const STATE_TRIAL_EXPIRED = 'trial_expired';

    public const STATE_ACTIVE = 'active';

    public const STATE_DUE = 'due';

    public const STATE_OVERDUE = 'overdue';

    public const STATE_CANCELLED = 'cancelled';

    protected $table = 'subscriptions';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionStatus::class,
            'trial_ends_at' => 'date',
            'current_period_start' => 'date',
            'current_period_end' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Tenant mode: trial or subscribed (cancelled counts as neither).
     */
    public function isTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trial;
    }

    public function isSubscribed(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    /**
     * Date-derived state shown in the console. "overdue" means an active
     * subscription whose paid period has passed without a renewal.
     */
    public function displayState(?CarbonInterface $today = null): string
    {
        $today ??= BillingClock::today();

        if ($this->status === SubscriptionStatus::Cancelled) {
            return self::STATE_CANCELLED;
        }

        if ($this->status === SubscriptionStatus::Trial) {
            return $this->trial_ends_at !== null && $this->trial_ends_at->lt($today)
                ? self::STATE_TRIAL_EXPIRED
                : self::STATE_TRIAL;
        }

        $end = $this->current_period_end;

        if ($end === null || $end->lt($today)) {
            return self::STATE_OVERDUE;
        }

        return $end->lte($today->copy()->addDays((int) config('billing.due_soon_days', 7)))
            ? self::STATE_DUE
            : self::STATE_ACTIVE;
    }

    /**
     * The last day the school keeps access. A trial and an active
     * subscription get a grace period after their end; a cancelled one
     * ("not renewed") keeps access only to the end of what it had, with no
     * grace. Null when the dates are missing, which never suspends.
     */
    public function accessEndsAt(): ?CarbonInterface
    {
        return match ($this->status) {
            SubscriptionStatus::Trial => $this->trial_ends_at?->copy()->addDays((int) config('billing.trial_grace_days', 7)),
            SubscriptionStatus::Active => $this->current_period_end?->copy()->addDays((int) config('billing.overdue_grace_days', 7)),
            SubscriptionStatus::Cancelled => $this->current_period_end ?? $this->trial_ends_at,
        };
    }

    /**
     * Access is over: the day after the last day of access has come.
     */
    public function accessLapsed(?CarbonInterface $today = null): bool
    {
        $today ??= BillingClock::today();
        $end = $this->accessEndsAt();

        return $end !== null && $end->lt($today);
    }

    /**
     * Past the trial or paid period but still inside the grace period.
     */
    public function inGrace(?CarbonInterface $today = null): bool
    {
        $today ??= BillingClock::today();
        $end = $this->endsAt();

        return $this->status !== SubscriptionStatus::Cancelled
            && $end !== null
            && $end->lt($today)
            && ! $this->accessLapsed($today);
    }

    /**
     * The date this subscription ends (trial end or paid period end).
     */
    public function endsAt(): ?CarbonInterface
    {
        return $this->status === SubscriptionStatus::Trial
            ? $this->trial_ends_at
            : $this->current_period_end;
    }
}
