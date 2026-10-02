<?php

namespace Modules\Core\App\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Core\App\Infrastructure\Whatsapp\PhoneNumber;
use Modules\Core\App\Infrastructure\Whatsapp\QueueWhatsappMessage;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Send a short test message from the school's WhatsApp to one number, so
 * the admin can see the link works. It goes through the same log and
 * queue as every other message.
 */
final class SendTestMessage
{
    public function __construct(
        private readonly QueueWhatsappMessage $queue,
        private readonly TenantContext $context,
    ) {}

    /**
     * @throws ValidationException when the number cannot be a mobile number
     */
    public function handle(string $phone): WhatsappMessage
    {
        if (PhoneNumber::normalize($phone) === null) {
            throw ValidationException::withMessages(['phone' => 'Nomor WhatsApp tidak sah. Contoh: 0812-3456-7890.']);
        }

        $school = $this->context->currentOrFail()->name;

        return $this->queue->handle(
            WhatsappMessage::KIND_TEST,
            'Pesan uji',
            $phone,
            "Ini pesan uji dari SIMAS {$school}. Jika pesan ini sampai, WhatsApp sekolah sudah terhubung.",
        );
    }
}
