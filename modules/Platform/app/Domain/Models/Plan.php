<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\PlanFactory;

/**
 * Subscription plan (provider master data). Internal to Platform.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property int $price_monthly
 * @property int $price_yearly
 * @property array<string, int>|null $limits named ceilings (students, staff_accounts, storage_mb); a missing key means no limit
 * @property array<int, string> $modules
 * @property bool $is_active
 * @property bool $is_public offered at sign-up; a non-public plan is only assigned by the provider
 * @property int $sort_order
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(PlanFactory::class)]
#[Fillable(['key', 'name', 'price_monthly', 'price_yearly', 'limits', 'modules', 'is_active', 'is_public', 'sort_order'])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $table = 'plans';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'limits' => 'array',
            'modules' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Plans that can be chosen for a (new) subscription.
     *
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeSelectable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNull('archived_at');
    }

    /**
     * Plans offered to a school signing up (selectable and public).
     *
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * The ceiling for one measure, or null when the plan sets none.
     */
    public function limit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function priceFor(BillingCycle $cycle): int
    {
        return $cycle === BillingCycle::Yearly ? $this->price_yearly : $this->price_monthly;
    }

    /**
     * Monthly-equivalent revenue for MRR (yearly price spread over 12).
     */
    public function monthlyEquivalent(BillingCycle $cycle): int
    {
        return (int) round($this->priceFor($cycle) / $cycle->months());
    }
}
