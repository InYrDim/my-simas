<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Infrastructure\Tenancy\TenantEvents;
use Modules\Platform\Database\Factories\TenantFactory;

/**
 * Internal Platform model — never exposed to other modules. Consumers
 * receive TenantData DTOs via TenantContext instead.
 *
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $domain
 * @property string $timezone
 * @property TenantStatus $status
 * @property array<string, mixed>|null $settings
 * @property string|null $billing_email where invoices and billing messages go
 * @property string|null $billing_name
 * @property bool $billing_exempt never billed, reminded or suspended for billing
 * @property SuspensionReason|null $suspended_reason null while active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[UseFactory(TenantFactory::class)]
#[Fillable(['name', 'slug', 'domain', 'timezone', 'status', 'settings', 'billing_email', 'billing_name', 'billing_exempt', 'suspended_reason'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'tenants';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'settings' => 'array',
            'billing_exempt' => 'boolean',
            'suspended_reason' => SuspensionReason::class,
        ];
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function isBillingExempt(): bool
    {
        return $this->billing_exempt;
    }

    protected static function booted(): void
    {
        static::created(function (Tenant $tenant): void {
            TenantEvents::dispatchTenantCreated($tenant->id);
        });
    }
}
