<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;

/**
 * Switch a school's WhatsApp off. The school can no longer link or send
 * from that moment, whatever the gateway says; the session's engine is
 * then stopped, keeping the linked device for when it is enabled again.
 */
final class DisableWhatsappInstance
{
    public function __construct(
        private readonly OpenWaClient $gateway,
    ) {}

    /**
     * @param  int  $decidedBy  provider user id
     *
     * @throws InstanceStatusException
     * @throws OpenWaException disabled in SIMAS, but the gateway did not stop the session
     */
    public function handle(WhatsappInstance $instance, ?string $note, int $decidedBy): WhatsappInstance
    {
        if ($instance->status !== WhatsappInstanceStatus::Active) {
            throw InstanceStatusException::notActive();
        }

        $instance->forceFill([
            'status' => WhatsappInstanceStatus::Disabled,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
            'note' => $note,
        ])->save();

        try {
            $this->gateway->stopSession((string) $instance->session_id, (string) $instance->api_key);
        } catch (OpenWaException $exception) {
            $instance->forceFill(['last_error' => $exception->forSchool()])->save();

            throw $exception;
        }

        $instance->forceFill(['connection_status' => 'disconnected', 'last_error' => null])->save();

        return $instance;
    }
}
