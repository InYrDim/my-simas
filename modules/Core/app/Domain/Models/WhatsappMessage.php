<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\Database\Factories\WhatsappMessageFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * One WhatsApp message a school tried to send, with its outcome. Stored
 * in `whatsapp_messages`; rows are a log and are never edited by hand.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $student_id the student it is about, when there is one
 * @property string $recipient_name
 * @property string|null $phone digits, international format (`62812…`)
 * @property string $kind `test`, or the key of a registered notice kind
 * @property string $body
 * @property WhatsappMessageStatus $status
 * @property string|null $gateway_message_id
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon $created_at
 */
#[UseFactory(WhatsappMessageFactory::class)]
#[Fillable(['student_id', 'recipient_name', 'phone', 'kind', 'body', 'status', 'gateway_message_id', 'error', 'sent_at'])]
class WhatsappMessage extends Model
{
    /** @use HasFactory<WhatsappMessageFactory> */
    use BelongsToTenant, HasFactory;

    /** The kind of a message sent from "Kirim pesan uji". */
    public const KIND_TEST = 'test';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => WhatsappMessageStatus::class, 'sent_at' => 'datetime'];
    }
}
