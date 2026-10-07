<?php

namespace Modules\Core\App\Infrastructure\Whatsapp;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Carbon;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Throwable;

/**
 * Hand one logged message to the school's WhatsApp and record the outcome.
 * The job carries its tenant (Platform's queue context), so the message is
 * looked up inside its own school.
 *
 * Waiting for the school's turn (pacing, daily limit, a pause after
 * failures) is not a failure: the job goes back to the queue for as long
 * as `retryUntil()` allows, and then the message is closed as not sent.
 * A gateway that is down or busy gets the message again, up to
 * `MAX_ERROR_ATTEMPTS` answers counted on the log entry; anything else is
 * final on the first answer.
 */
final class DeliverWhatsappMessage implements ShouldQueue
{
    use Queueable;

    /** No limit on turns: `retryUntil()` ends the waiting, failures are counted on the message. */
    public int $tries = 0;

    /** Failures worth a retry that are tried before the message is marked failed. */
    private const MAX_ERROR_ATTEMPTS = 3;

    /** Seconds to wait before the second and the third attempt. */
    private const BACKOFF = [30, 120];

    private const TOO_LATE = 'Pengiriman tertunda terlalu lama.';

    /** Hours a message may wait for its turn. */
    private const WAIT_HOURS = 12;

    /** Unix time the job was queued: `retryUntil()` is asked again on every run, so it needs a fixed start. */
    public readonly int $queuedAt;

    public function __construct(
        public readonly int $messageId,
    ) {
        $this->queuedAt = now()->getTimestamp();
    }

    /**
     * The moment the job stops being tried again.
     */
    public function retryUntil(): Carbon
    {
        return Carbon::createFromTimestamp($this->queuedAt)->addHours(self::WAIT_HOURS);
    }

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

        if ($result->throttled) {
            $this->waitForTurn($message, $result->retryAfter ?? 60, $result->error);

            return;
        }

        if ($result->retryable && $this->canRetry()) {
            $errorAttempts = $message->error_attempts + 1;

            if ($errorAttempts < self::MAX_ERROR_ATTEMPTS) {
                $message->update(['error' => $result->error, 'error_attempts' => $errorAttempts]);
                $this->release(self::BACKOFF[$errorAttempts - 1] ?? 120);

                return;
            }

            $message->update(['error_attempts' => $errorAttempts]);
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
        $tooLate = $exception instanceof MaxAttemptsExceededException;

        WhatsappMessage::query()
            ->whereKey($this->messageId)
            ->where('status', WhatsappMessageStatus::Pending)
            ->update($tooLate ? [
                'status' => WhatsappMessageStatus::Unsent,
                'error' => self::TOO_LATE,
            ] : [
                'status' => WhatsappMessageStatus::Failed,
                'error' => 'Pesan tidak bisa diproses. Hubungi penyedia layanan.',
            ]);
    }

    /**
     * Put the job back until the school's turn. Past the deadline, or on a
     * driver that cannot release (sync), the message is closed as not sent
     * instead of staying "in the queue" for ever.
     */
    private function waitForTurn(WhatsappMessage $message, int $seconds, ?string $reason): void
    {
        if ($this->job === null || $this->job instanceof SyncJob) {
            $message->update(['status' => WhatsappMessageStatus::Unsent, 'error' => $reason ?? self::TOO_LATE]);

            return;
        }

        if (now()->addSeconds($seconds)->greaterThan($this->retryUntil())) {
            $message->update(['status' => WhatsappMessageStatus::Unsent, 'error' => self::TOO_LATE]);

            return;
        }

        $this->release($seconds);
    }

    /**
     * Whether a worker will pick the job up again. The sync driver runs a
     * job once, inside the request: there is no later.
     */
    private function canRetry(): bool
    {
        return $this->job !== null && ! $this->job instanceof SyncJob;
    }
}
