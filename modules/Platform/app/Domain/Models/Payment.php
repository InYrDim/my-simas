<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\PaymentFactory;

/**
 * One attempt to pay an invoice. Created pending by a PaymentGateway and
 * turned paid/failed/expired only through SubscriptionManager::settle().
 * Internal to Platform.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $tenant_id
 * @property int $amount
 * @property string $gateway
 * @property string|null $method
 * @property PaymentStatus $status
 * @property string|null $reference
 * @property string|null $external_id
 * @property Carbon|null $paid_on
 * @property Carbon|null $paid_at
 * @property int|null $confirmed_by
 * @property string|null $note
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice|null $invoice
 */
#[UseFactory(PaymentFactory::class)]
#[Fillable(['invoice_id', 'tenant_id', 'amount', 'gateway', 'method', 'status', 'reference', 'external_id', 'paid_on', 'paid_at', 'confirmed_by', 'note', 'meta', 'expires_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $table = 'payments';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_on' => 'date',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
