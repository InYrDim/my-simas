<?php

namespace Modules\Platform\App\Infrastructure\Tenancy;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * Stamps queued jobs with the dispatching tenant and restores that
 * context when the job runs.
 *
 * Save/restore semantics (NOT forget): the sync driver executes jobs
 * inside the dispatching request, so the caller's context must survive.
 * Restore always resets to the caller's state — null when the dispatch
 * itself was central — so sequential jobs never inherit each other's
 * context.
 *
 * Previous contexts are tracked per job OBJECT via SplObjectStorage:
 * SyncJob::getJobId() returns '' on every job, ids are not reliable.
 */
final class TenantQueueContext
{
    /**
     * Payload key carrying the dispatching tenant id.
     */
    public const PAYLOAD_KEY = 'tenant_id';

    /**
     * @var \SplObjectStorage<Job, array{0: ?string, 1: bool}>
     */
    private \SplObjectStorage $previous;

    public function __construct(
        private readonly TenantContext $context,
    ) {
        $this->previous = new \SplObjectStorage;
    }

    public function register(): void
    {
        Queue::createPayloadUsing(function (?string $connection, ?string $queue, array $payload): array {
            $tenantId = $this->context->id();

            return $tenantId === null ? [] : [self::PAYLOAD_KEY => $tenantId];
        });

        Queue::before(function (JobProcessing $event): void {
            $job = $event->job;

            // Remember what the caller was running in (sync driver).
            $currentId = $this->context->id();
            $this->previous[$job] = [$currentId, true];

            $tenantId = $job->payload()[self::PAYLOAD_KEY] ?? null;

            if (is_string($tenantId) && $tenantId !== '') {
                $this->context->set($tenantId);
            } else {
                $this->context->forget();
            }
        });

        $restore = function (JobProcessing|JobProcessed|JobFailed|JobExceptionOccurred $event): void {
            $job = $event->job;

            if (! isset($this->previous[$job])) {
                return;
            }

            [$previousId] = $this->previous[$job];
            $this->previous->offsetUnset($job);

            if ($previousId !== null) {
                $this->context->set($previousId);
            } else {
                $this->context->forget();
            }
        };

        Queue::after($restore);
        Queue::failing($restore);
        Queue::exceptionOccurred($restore);
    }
}
