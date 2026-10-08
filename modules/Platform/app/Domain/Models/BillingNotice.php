<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\BillingNoticeFactory;

/**
 * Record of one billing message to a school's billing contact: the
 * delivery history in the console, and the idempotency key for scheduled
 * messages (one row per subscription, kind and anchor date). Central
 * table: queries from the school side must filter tenant_id themselves.
 * Internal to Platform.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $subscription_id
 * @property int|null $invoice_id
 * @property BillingNoticeKind $kind
 * @property string $channel
 * @property string|null $recipient
 * @property BillingNoticeStatus $status
 * @property Carbon|null $anchor_date
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(BillingNoticeFactory::class)]
#[Fillable(['tenant_id', 'subscription_id', 'invoice_id', 'kind', 'channel', 'recipient', 'status', 'anchor_date', 'error', 'sent_at'])]
class BillingNotice extends Model
{
    /** @use HasFactory<BillingNoticeFactory> */
    use HasFactory;

    protected $table = 'billing_notices';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => BillingNoticeKind::class,
            'status' => BillingNoticeStatus::class,
            'anchor_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }
}
