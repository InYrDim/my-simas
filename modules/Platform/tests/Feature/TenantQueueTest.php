<?php

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Infrastructure\Tenancy\TenantQueueContext;

/**
 * Probe job capturing the tenant id it ran with. No constructor: the
 * old "reset in __construct" pattern erased captures set by an earlier
 * job of the same test.
 */
class ProbeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** @var array<int, string|null> */
    public static array $captured = [];

    public static function reset(): void
    {
        static::$captured = [];
    }

    public function handle(TenantContext $context): void
    {
        static::$captured[] = $context->id();
    }
}

/**
 * Probe listener for event-based propagation. Registered via class
 * string — Laravel invokes handle() directly (no container injection),
 * so it must not require extra parameters.
 */
class ProbeListener
{
    /** @var array<int, ?string> */
    public static array $captured = [];

    public static function reset(): void
    {
        static::$captured = [];
    }

    public function handle(ProbeTenantEvent $event): void
    {
        // Class listeners are invoked directly (no container injection):
        // resolve the context explicitly.
        static::$captured[] = app(TenantContext::class)->id();
    }
}

beforeEach(function () {
    ProbeJob::reset();
    ProbeListener::reset();

    // flushState() clears ALL createPayloadUsing hooks between tests,
    // which would silently unregister ours. Re-register per test.
    app(TenantQueueContext::class)->register();
});

function tenantForQueues(string $slug): string
{
    /** @var Tenant $tenant */
    $tenant = Tenant::query()->create(['name' => ucfirst($slug), 'slug' => $slug]);

    return $tenant->id;
}

/**
 * Dispatch must happen INSIDE the callback body and its return value
 * must be discarded. A bare `fn () => Job::dispatch()` RETURNS the
 * PendingDispatch, whose destructor defers the actual push until after
 * run() has restored the context — the payload would be stamped with
 * the wrong (restored) tenant.
 */
it('stamps queued payloads with the dispatching tenant id', function () {
    $a = tenantForQueues('sekolah-a');
    $context = app(TenantContext::class);

    $captured = null;

    Queue::after(function ($event) use (&$captured): void {
        $captured = $event->job->payload()['tenant_id'] ?? null;
    });

    $context->run($a, function (): void {
        ProbeJob::dispatch();
    });

    expect($captured)->toBe($a);
});

it('runs queued jobs with the dispatching tenant context', function () {
    $a = tenantForQueues('sekolah-a');
    $context = app(TenantContext::class);

    $context->run($a, function (): void {
        ProbeJob::dispatch();
    });

    expect(ProbeJob::$captured)->toBe([$a]);
});

it('keeps the dispatching context after a sync job completes', function () {
    $a = tenantForQueues('sekolah-a');
    $context = app(TenantContext::class);

    $context->run($a, function () use ($context, $a): void {
        ProbeJob::dispatch();

        // sync driver must NOT have nuked the caller's context
        expect($context->id())->toBe($a);
    });

    expect($context->id())->toBeNull()
        ->and(ProbeJob::$captured)->toBe([$a]);
});

it('does not let sequential jobs inherit each other\'s context', function () {
    $a = tenantForQueues('sekolah-a');
    $b = tenantForQueues('sekolah-b');
    $context = app(TenantContext::class);

    // Simulate worker behaviour: two jobs processed back-to-back on the
    // same process. Without proper restore, the second would see A.
    $context->run($a, function (): void {
        ProbeJob::dispatch();
    });
    $context->run($b, function (): void {
        ProbeJob::dispatch();
    });

    expect(ProbeJob::$captured)->toBe([$a, $b]);
});

it('runs jobs without tenant when dispatched centrally', function () {
    tenantForQueues('sekolah-a');
    $context = app(TenantContext::class);

    ProbeJob::dispatch();

    expect(ProbeJob::$captured)->toBe([null]);
});

it('propagates tenant context to queued listeners via events', function () {
    $a = tenantForQueues('sekolah-a');

    Event::listen(ProbeTenantEvent::class, ProbeListener::class);

    app(TenantContext::class)->run($a, function (): void {
        event(new ProbeTenantEvent);
    });

    expect(ProbeListener::$captured)->toBe([$a]);
});

it('propagates no tenant context to listeners when dispatched centrally', function () {
    tenantForQueues('sekolah-a');

    Event::listen(ProbeTenantEvent::class, ProbeListener::class);

    event(new ProbeTenantEvent);

    expect(ProbeListener::$captured)->toBe([null]);
});

class ProbeTenantEvent
{
    public string $at;

    public function __construct()
    {
        $this->at = now()->toDateTimeString();
    }
}
