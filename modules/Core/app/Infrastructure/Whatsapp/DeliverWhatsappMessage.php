<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Throwable;

/**
 * Hand one logged message to the school's WhatsApp and record the outcome.
 * The job carries its tenant (Platform's queue context), so the message is
 * looked up inside its own school.
 *
 * A gateway that is down or busy gets the message again, up to `$tries`
 * times; anything else is final on the first answer.
 */
final class DeliverWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Seconds to wait before the second and the third attempt. */
    private const BACKOFF = [30, 120];

    public function __construct(
        public readonly int $messageId,
    ) {}

    public function handle(WhatsappChannel $whatsapp): void
    {
        $message = WhatsappMessage::query()->find($this->messageId);

        // Sent already, or gone: a job that runs twice must not send twice.
        if ($message === null || $message->status !== WhatsappMessageStatus::Pending) {
            return;
        }

        $result = $whatsapp->sendText((string) $message->phone, $message->body);

        if ($result->sent) {
            $message->update([
                'status' => WhatsappMessageStatus::Sent,
                'gateway_message_id' => $result->messageId,
                'error' => null,
                'sent_at' => now(),
            ]);

            return;
        }

        if ($result->retryable && $this->canRetry()) {
            $message->update(['error' => $result->error]);
            $this->release(self::BACKOFF[$this->attempts() - 1] ?? 120);

            return;
        }

        $message->update([
            'status' => $result->unavailable ? WhatsappMessageStatus::Unsent : WhatsappMessageStatus::Failed,
            'error' => $result->error,
        ]);
    }

    /**
     * The job itself broke (a bug, a missing table, ...) and the worker
     * gave up on it: close the log entry, or it would read "in the queue"
     * for ever. The cause is in `failed_jobs`, not in front of the school.
     */
    public function failed(?Throwable $exception): void
    {
        WhatsappMessage::query()
            ->whereKey($this->messageId)
            ->where('status', WhatsappMessageStatus::Pending)
            ->update([
                'status' => WhatsappMessageStatus::Failed,
                'error' => 'Pesan tidak bisa diproses. Hubungi penyedia layanan.',
            ]);
    }

    /**
     * Whether a worker will pick the job up again. The sync driver runs a
     * job once, inside the request: there is no later.
     */
    private function canRetry(): bool
    {
        return $this->job !== null
            && ! $this->job instanceof SyncJob
            && $this->attempts() < $this->tries;
    }
}
