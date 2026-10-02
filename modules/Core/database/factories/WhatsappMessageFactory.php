<?php

namespace Modules\Core\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\WhatsappMessage;

/**
 * Defaults to a test message waiting in the queue.
 *
 * @extends Factory<WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    protected $model = WhatsappMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recipient_name' => 'Pesan uji',
            'phone' => '6281234567890',
            'kind' => WhatsappMessage::KIND_TEST,
            'body' => 'Pesan uji dari SIMAS.',
            'status' => WhatsappMessageStatus::Pending,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsappMessageStatus::Sent,
            'gateway_message_id' => 'true_6281234567890@c.us_'.fake()->regexify('[A-F0-9]{16}'),
            'sent_at' => now(),
        ]);
    }

    public function failed(string $error = 'Gateway WhatsApp tidak bisa dihubungi.'): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsappMessageStatus::Failed,
            'error' => $error,
        ]);
    }
}
