<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;

/**
 * Switch a disabled school's WhatsApp back on. Nothing is started: the
 * school presses "Hubungkan", and the device linked before reconnects
 * without a new QR.
 */
final class EnableWhatsappInstance
{
    /**
     * @param  int  $decidedBy  provider user id
     *
     * @throws InstanceStatusException
     */
    public function handle(WhatsappInstance $instance, int $decidedBy): WhatsappInstance
    {
        if ($instance->status !== WhatsappInstanceStatus::Disabled) {
            throw InstanceStatusException::notDisabled();
        }

        $instance->forceFill([
            'status' => WhatsappInstanceStatus::Active,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
            'note' => null,
            'last_error' => null,
        ])->save();

        return $instance;
    }
}
