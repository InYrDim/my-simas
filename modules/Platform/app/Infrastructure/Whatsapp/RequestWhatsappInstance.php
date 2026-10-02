<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;

/**
 * Record a school's request for WhatsApp. One row per school: asking again
 * never duplicates it, and only a rejected request goes back to review.
 *
 * With the provider's automatic approval on, a request that just became
 * pending is approved right away. When the gateway refuses, it simply
 * waits for the provider, who sees the reason in the console.
 */
final class RequestWhatsappInstance
{
    public function __construct(
        private readonly ApproveWhatsappInstance $approve,
    ) {}

    /**
     * @param  int  $requestedBy  school user id
     */
    public function handle(string $tenantId, int $requestedBy): WhatsappInstance
    {
        $instance = WhatsappInstance::query()->firstOrCreate(
            ['tenant_id' => $tenantId],
            ['status' => WhatsappInstanceStatus::Pending, 'requested_by' => $requestedBy, 'requested_at' => now()],
        );

        if (! $instance->wasRecentlyCreated) {
            if ($instance->status !== WhatsappInstanceStatus::Rejected) {
                return $instance;
            }

            // The provider's note stays so the reviewer sees what was asked.
            $instance->forceFill([
                'status' => WhatsappInstanceStatus::Pending,
                'requested_by' => $requestedBy,
                'requested_at' => now(),
                'decided_by' => null,
                'decided_at' => null,
            ])->save();
        }

        if (ProviderSetting::read(ProviderSetting::WHATSAPP_AUTO_APPROVE, false) === true) {
            try {
                $this->approve->handle($instance, null);
            } catch (OpenWaException) {
                // Left pending; the reason is on the row for the provider.
            }
        }

        return $instance;
    }
}
