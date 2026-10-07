<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Infrastructure\Whatsapp\CredentialCast;
use Modules\Platform\Database\Factories\WhatsappInstanceFactory;

/**
 * A tenant's WhatsApp instance (one per tenant): the school's request, the
 * provider's decision and the OpenWA session that carries its number.
 * Internal to Platform, queried by explicit tenant id like Subscription.
 *
 * `api_key` is the session-scoped OpenWA key. It is encrypted at rest
 * with OPENWA_CREDENTIALS_KEY, hidden from every array/JSON form, and
 * never handed to another module or to a page.
 *
 * @property int $id
 * @property string $tenant_id
 * @property WhatsappInstanceStatus $status
 * @property int|null $requested_by school user id
 * @property Carbon|null $requested_at
 * @property int|null $decided_by provider user id
 * @property Carbon|null $decided_at
 * @property string|null $note why it was rejected or disabled
 * @property string|null $session_id
 * @property string|null $session_name
 * @property string|null $api_key
 * @property string|null $api_key_id
 * @property string|null $connection_status OpenWA's session status, as last seen
 * @property string|null $phone
 * @property string|null $push_name
 * @property Carbon|null $connected_at
 * @property Carbon|null $last_checked_at
 * @property string|null $last_error
 * @property int|null $risk_acknowledged_by school user id who accepted the risk warning
 * @property Carbon|null $risk_acknowledged_at
 * @property-read Tenant|null $tenant
 */
#[UseFactory(WhatsappInstanceFactory::class)]
#[Fillable([
    'tenant_id', 'status', 'requested_by', 'requested_at', 'decided_by', 'decided_at', 'note',
    'session_id', 'session_name', 'api_key', 'api_key_id',
    'connection_status', 'phone', 'push_name', 'connected_at', 'last_checked_at', 'last_error',
    'risk_acknowledged_by', 'risk_acknowledged_at',
])]
#[Hidden(['api_key', 'api_key_id'])]
class WhatsappInstance extends Model
{
    /** @use HasFactory<WhatsappInstanceFactory> */
    use HasFactory;

    protected $table = 'whatsapp_instances';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WhatsappInstanceStatus::class,
            'api_key' => CredentialCast::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'risk_acknowledged_at' => 'datetime',
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
     * Approved, not disabled, and holding a session with its key: the
     * gateway can be called for this school.
     */
    public function usable(): bool
    {
        return $this->status === WhatsappInstanceStatus::Active
            && $this->session_id !== null
            && $this->getRawOriginal('api_key') !== null;
    }
}
