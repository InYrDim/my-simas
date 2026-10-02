<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;

/**
 * Reject a school's WhatsApp request. Nothing is created on the gateway;
 * the school sees the note and may ask again.
 */
final class RejectWhatsappInstance
{
    /**
     * @param  int  $decidedBy  provider user id
     *
     * @throws InstanceStatusException
     */
    public function handle(WhatsappInstance $instance, ?string $note, int $decidedBy): WhatsappInstance
    {
        if ($instance->status !== WhatsappInstanceStatus::Pending) {
            throw InstanceStatusException::notPending();
        }

        $instance->forceFill([
            'status' => WhatsappInstanceStatus::Rejected,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
            'note' => $note,
            'last_error' => null,
        ])->save();

        return $instance;
    }
}
