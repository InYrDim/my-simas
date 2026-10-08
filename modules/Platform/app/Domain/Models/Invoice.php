<?php

namespace Modules\Platform\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Domain\Support\BillingClock;
use Modules\Platform\Database\Factories\InvoiceFactory;

/**
 * Subscription invoice. Plan name, cycle and amount are snapshots taken at
 * issue time. Internal to Platform.
 *
 * @property int $id
 * @property string $number
 * @property string $tenant_id
 * @property int $subscription_id
 * @property int $plan_id
 * @property string $plan_name
 * @property BillingCycle $billing_cycle
 * @property InvoiceKind $kind
 * @property int $amount
 * @property InvoiceStatus $status
 * @property Carbon $issued_at
 * @property Carbon $due_at
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Subscription|null $subscription
 */
#[UseFactory(InvoiceFactory::class)]
#[Fillable(['number', 'tenant_id', 'subscription_id', 'plan_id', 'plan_name', 'billing_cycle', 'kind', 'amount', 'status', 'issued_at', 'due_at', 'period_start', 'period_end', 'paid_at'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $table = 'invoices';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'kind' => InvoiceKind::class,
            'status' => InvoiceStatus::class,
            'amount' => 'integer',
            'issued_at' => 'date',
            'due_at' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Display state: an unpaid invoice past its due date is overdue.
     */
    public function displayState(?CarbonInterface $today = null): string
    {
        $today ??= BillingClock::today();

        if ($this->status === InvoiceStatus::Unpaid && $this->due_at->lt($today)) {
            return 'overdue';
        }

        return $this->status->value;
    }
}
