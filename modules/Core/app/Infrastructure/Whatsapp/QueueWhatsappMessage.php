<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\WhatsappMessage;

/**
 * The one way a WhatsApp message leaves Core: it is written to the log
 * first, then queued for delivery. A message without a usable number is
 * logged as such and never queued.
 */
final class QueueWhatsappMessage
{
    /**
     * @param  string  $kind  `test`, or the key of a registered notice kind
     * @param  string|null  $phone  as typed; normalised here
     * @param  int|null  $studentId  the student the message is about
     */
    public function handle(string $kind, string $recipientName, ?string $phone, string $body, ?int $studentId = null): WhatsappMessage
    {
        $number = PhoneNumber::normalize($phone);

        $message = WhatsappMessage::query()->create([
            'student_id' => $studentId,
            'recipient_name' => $recipientName,
            'phone' => $number,
            'kind' => $kind,
            'body' => $body,
            'status' => $number === null ? WhatsappMessageStatus::NoRecipient : WhatsappMessageStatus::Pending,
        ]);

        if ($number !== null) {
            DeliverWhatsappMessage::dispatch($message->id);
        }

        return $message;
    }
}
