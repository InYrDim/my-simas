<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Core\App\Infrastructure\Whatsapp\DeliverWhatsappMessage;
use Modules\Core\App\Infrastructure\Whatsapp\QueueWhatsappMessage;
use Modules\Platform\App\Contracts\DTOs\WhatsappSendResult;
use Modules\Platform\App\Contracts\DTOs\WhatsappState;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Infrastructure\Tenancy\TenantQueueContext;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Database\Factories\WhatsappInstanceFactory;
use RuntimeException;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/Support/helpers.php';

/*
 * Sending through the school's WhatsApp (fase 9, stage 3): the test
 * message, the log, the delivery job and the history on the page. The
 * gateway is faked: no message leaves the test.
 */

const WHATSAPP_SEND_KEY = 'owa_k1_send-session-key-for-tests';

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        // With a Vite dev server running, a full page load asks it to render
        // (SSR) over HTTP, which the stray-request guard would refuse.
        'inertia.ssr.enabled' => false,
        'services.openwa.base_url' => 'https://wa.test',
        'services.openwa.admin_api_key' => 'owa_k1_admin-key-for-tests',
        'services.openwa.credentials_key' => str_repeat('ab', 32),
        // Pacing is Platform's and has its own tests: here every turn is free.
        'services.openwa.pace_min' => 0,
        'services.openwa.pace_max' => 0,
    ]);

    // flushState() clears the queue payload hooks between tests.
    app(TenantQueueContext::class)->register();
});

/**
 * A school whose WhatsApp is linked, signed in as its admin.
 */
function linkedSchool(string $slug): Tenant
{
    $tenant = schoolAs($slug);
    linkWhatsapp($tenant);

    return $tenant;
}

function linkWhatsapp(Tenant $tenant): WhatsappInstance
{
    return WhatsappInstanceFactory::new()->forTenant($tenant->id)->connected('628111000111')->create([
        'api_key' => WHATSAPP_SEND_KEY,
    ]);
}

/**
 * The gateway accepts every message and reports the session linked.
 */
function gatewayAccepts(): void
{
    Http::fake(fn (Request $request) => str_ends_with($request->url(), '/messages/send-text')
        ? Http::response(['messageId' => 'true_628@c.us_3EB0', 'timestamp' => 1782813600], 201)
        : Http::response(['status' => 'ready', 'phone' => '628111000111']));
}

function sendCalls(): int
{
    return Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/messages/send-text'))->count();
}

it('sends a test message: logged, delivered by the job, and marked as sent', function () {
    gatewayAccepts();
    $tenant = linkedSchool('wa-test-ok');
    $instance = WhatsappInstance::query()->where('tenant_id', $tenant->id)->sole();

    post(school($tenant->slug, '/integrasi/whatsapp/uji'), ['phone' => '0812-3456-7890'])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    $message = inSchool($tenant, fn () => WhatsappMessage::query()->sole());

    expect($message->status)->toBe(WhatsappMessageStatus::Sent)
        ->and($message->kind)->toBe('test')
        ->and($message->phone)->toBe('6281234567890')
        ->and($message->gateway_message_id)->toBe('true_628@c.us_3EB0')
        ->and($message->sent_at)->not->toBeNull()
        ->and($message->body)->toContain($tenant->name);

    Http::assertSent(fn (Request $request): bool => $request->url() === "https://wa.test/api/sessions/{$instance->session_id}/messages/send-text"
        && $request->header('X-API-Key') === [WHATSAPP_SEND_KEY]
        && $request['chatId'] === '6281234567890@c.us'
        && $request['text'] === $message->body);
});

it('queues the delivery instead of sending inside the request', function () {
    Queue::fake();
    $tenant = linkedSchool('wa-test-queued');

    post(school($tenant->slug, '/integrasi/whatsapp/uji'), ['phone' => '081234567890'])->assertSessionHasNoErrors();

    $message = inSchool($tenant, fn () => WhatsappMessage::query()->sole());

    expect($message->status)->toBe(WhatsappMessageStatus::Pending);

    Queue::assertPushed(DeliverWhatsappMessage::class, fn (DeliverWhatsappMessage $job): bool => $job->messageId === $message->id);
    Http::assertNothingSent();
});

it('refuses a test message to a number that cannot be one', function (array $payload) {
    $tenant = linkedSchool('wa-test-invalid');

    post(school($tenant->slug, '/integrasi/whatsapp/uji'), $payload)->assertSessionHasErrors('phone');

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->count()))->toBe(0);

    Http::assertNothingSent();
})->with([
    'missing' => [[]],
    'too short' => [['phone' => '0812']],
    'words' => [['phone' => 'nomor saya']],
]);

it('limits how many test messages an admin sends in a minute', function () {
    gatewayAccepts();
    $tenant = linkedSchool('wa-test-limit');

    foreach (range(1, 5) as $ignored) {
        post(school($tenant->slug, '/integrasi/whatsapp/uji'), ['phone' => '081234567890'])->assertSessionHasNoErrors();
    }

    post(school($tenant->slug, '/integrasi/whatsapp/uji'), ['phone' => '081234567890'])->assertSessionHasErrors('phone');

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->count()))->toBe(5)
        ->and(sendCalls())->toBe(5);
});

it('keeps the test message away from other roles and guests', function (string $role) {
    $tenant = schoolAs("wa-test-{$role}", $role);
    linkWhatsapp($tenant);

    post(school($tenant->slug, '/integrasi/whatsapp/uji'), ['phone' => '081234567890'])->assertForbidden();

    signOutOfSchool();

    post(school($tenant->slug, '/integrasi/whatsapp/uji'), ['phone' => '081234567890'])->assertRedirect();

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->count()))->toBe(0);

    Http::assertNothingSent();
})->with(['guru', 'staf-tu', 'siswa']);

it('logs a message as not sent, without calling the gateway, while WhatsApp is not linked', function (?string $factoryState) {
    $tenant = schoolAs('wa-unlinked');

    if ($factoryState !== null) {
        WhatsappInstanceFactory::new()->forTenant($tenant->id)->{$factoryState}()->create();
    }

    $message = inSchool($tenant, fn () => app(QueueWhatsappMessage::class)->handle('test', 'Pesan uji', '081234567890', 'Halo'));

    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Unsent)
        ->and($message->error)->toBe('WhatsApp sekolah belum terhubung.');

    Http::assertNothingSent();
})->with([
    'never asked' => [null],
    'waiting for approval' => ['pending'],
    'approved, not linked' => ['active'],
    'switched off by the provider' => ['disabled'],
]);

it('logs a message without a usable number and never queues it', function (?string $phone) {
    Queue::fake();
    $tenant = linkedSchool('wa-no-number');

    $message = inSchool($tenant, fn () => app(QueueWhatsappMessage::class)->handle('test', 'Wali Budi', $phone, 'Halo', 7));

    expect($message->status)->toBe(WhatsappMessageStatus::NoRecipient)
        ->and($message->phone)->toBeNull()
        ->and($message->student_id)->toBe(7)
        ->and($message->recipient_name)->toBe('Wali Budi');

    Queue::assertNothingPushed();
})->with(['empty' => [null], 'unusable' => ['-']]);

it('marks a message failed when the gateway refuses it for good', function () {
    Http::fake(['wa.test/*' => Http::response(['message' => 'Number not on WhatsApp'], 422)]);
    $tenant = linkedSchool('wa-refused');

    $message = inSchool($tenant, fn () => app(QueueWhatsappMessage::class)->handle('test', 'Pesan uji', '081234567890', 'Halo'));

    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Failed)
        ->and($message->error)->toContain('422')
        ->and($message->sent_at)->toBeNull()
        ->and(sendCalls())->toBe(1);
});

it('tries a message again while the gateway is down, then gives up', function (callable $down) {
    Http::fake($down);
    $tenant = linkedSchool('wa-retry');
    $message = inSchool($tenant, fn () => WhatsappMessage::factory()->create());

    $run = function (int $attempt) use ($tenant, $message): DeliverWhatsappMessage {
        $job = (new DeliverWhatsappMessage($message->id))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;

        inSchool($tenant, fn () => $job->handle(app(WhatsappChannel::class)));

        return $job;
    };

    $run(1)->assertReleased(delay: 30);
    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Pending)
        ->and($message->error)->not->toBeNull();

    $run(2)->assertReleased(delay: 120);
    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Pending);

    $run(3)->assertNotReleased();
    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Failed)
        ->and($message->error)->toContain('Gateway WhatsApp');
})->with([
    'server error' => [fn () => Http::response(null, 503)],
    'unreachable' => [fn () => throw new ConnectionException('cURL error 28')],
]);

it('delivers on a later attempt once the gateway is back', function () {
    $answers = [Http::response(null, 502), Http::response(['messageId' => 'late-1'], 201)];
    Http::fake(function () use (&$answers) {
        return array_shift($answers);
    });

    $tenant = linkedSchool('wa-recover');
    $message = inSchool($tenant, fn () => WhatsappMessage::factory()->create());

    foreach ([1, 2] as $attempt) {
        $job = (new DeliverWhatsappMessage($message->id))->withFakeQueueInteractions();
        $job->job->attempts = $attempt;
        inSchool($tenant, fn () => $job->handle(app(WhatsappChannel::class)));
    }

    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Sent)
        ->and($message->gateway_message_id)->toBe('late-1')
        ->and($message->error)->toBeNull();
});

it('does not send a message twice when its job runs twice', function () {
    gatewayAccepts();
    $tenant = linkedSchool('wa-once');
    $message = inSchool($tenant, fn () => WhatsappMessage::factory()->create());

    inSchool($tenant, function () use ($message): void {
        DeliverWhatsappMessage::dispatchSync($message->id);
        DeliverWhatsappMessage::dispatchSync($message->id);
    });

    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Sent)
        ->and(sendCalls())->toBe(1);
});

it('shows the log on the page, newest first, with the number masked', function () {
    gatewayAccepts();
    $tenant = linkedSchool('wa-history');

    inSchool($tenant, function (): void {
        WhatsappMessage::factory()->sent()->create(['phone' => '6281234567890', 'recipient_name' => 'Wali Budi']);
        WhatsappMessage::factory()->failed('Gateway WhatsApp menjawab galat 502.')->create(['phone' => '6285711112222']);
    });

    $response = get(school($tenant->slug, '/integrasi/whatsapp'))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->has('history', 2)
        ->where('history.0.status', 'failed')
        ->where('history.0.error', 'Gateway WhatsApp menjawab galat 502.')
        ->where('history.0.to', '62857••••2222')
        ->where('history.0.kind', 'Pesan uji')
        ->where('history.1.status', 'sent')
        ->where('history.1.recipient', 'Wali Budi')
        ->where('history.1.to', '62812••••7890')
        ->where('pagination.total', 2)
        ->missing('history.0.body')
        ->missing('history.0.phone')
    );

    expect($response->getContent())
        ->not->toContain('6281234567890')
        ->not->toContain('6285711112222')
        ->not->toContain('owa_k1_');
});

it('pages the log', function () {
    gatewayAccepts();
    $tenant = linkedSchool('wa-pages');

    inSchool($tenant, fn () => WhatsappMessage::factory()->sent()->count(17)->create());

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->has('history', 15)
        ->where('pagination.total', 17)
        ->where('pagination.lastPage', 2)
    );

    get(school($tenant->slug, '/integrasi/whatsapp?page=2'))->assertInertia(fn (Assert $page) => $page
        ->has('history', 2)
        ->where('pagination.page', 2)
    );
});

it('keeps each schools log and number to itself', function () {
    gatewayAccepts();

    $other = TenantFactory::new()->create(['slug' => 'wa-log-other']);
    $otherInstance = linkWhatsapp($other);
    $foreign = inSchool($other, fn () => WhatsappMessage::factory()->create(['recipient_name' => 'Wali Sekolah Lain']));

    $tenant = linkedSchool('wa-log-own');
    $instance = WhatsappInstance::query()->where('tenant_id', $tenant->id)->sole();

    // The other school's message id, delivered from this school: not found.
    inSchool($tenant, fn () => DeliverWhatsappMessage::dispatchSync($foreign->id));

    expect(inSchool($other, fn () => $foreign->refresh()->status))->toBe(WhatsappMessageStatus::Pending)
        ->and(sendCalls())->toBe(0);

    post(school($tenant->slug, '/integrasi/whatsapp/uji'), ['phone' => '081234567890'])->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), "/sessions/{$instance->session_id}/messages/"));
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), "/sessions/{$otherInstance->session_id}/"));

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->has('history', 1)
        ->where('history.0.recipient', 'Pesan uji')
    );

    expect(inSchool($other, fn () => WhatsappMessage::query()->count()))->toBe(1);
});

it('delivers from a queue worker, outside any request', function () {
    gatewayAccepts();
    config(['queue.default' => 'database']);

    $tenant = linkedSchool('wa-worker');

    post(school($tenant->slug, '/integrasi/whatsapp/uji'), ['phone' => '081234567890'])->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => WhatsappMessage::query()->sole()->status))->toBe(WhatsappMessageStatus::Pending)
        ->and(sendCalls())->toBe(0);

    // The worker has no tenant of its own: the job brings it.
    app(TenantContext::class)->forget();

    artisan('queue:work', ['--once' => true])->assertSuccessful();

    $message = inSchool($tenant, fn () => WhatsappMessage::query()->sole());

    expect($message->status)->toBe(WhatsappMessageStatus::Sent)
        ->and(sendCalls())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(0);
});

it('closes the log entry when the job itself breaks', function () {
    $tenant = linkedSchool('wa-broken-job');
    $pending = inSchool($tenant, fn () => WhatsappMessage::factory()->create());
    $sent = inSchool($tenant, fn () => WhatsappMessage::factory()->sent()->create());

    inSchool($tenant, function () use ($pending, $sent): void {
        (new DeliverWhatsappMessage($pending->id))->failed(new RuntimeException('boom'));
        (new DeliverWhatsappMessage($sent->id))->failed(new RuntimeException('boom'));
    });

    expect($pending->refresh()->status)->toBe(WhatsappMessageStatus::Failed)
        ->and($pending->error)->not->toContain('boom')
        ->and($sent->refresh()->status)->toBe(WhatsappMessageStatus::Sent);
});

/**
 * A channel that answers `sendText` from a script, for the paths the real
 * gateway cannot be told to take.
 *
 * @param  list<WhatsappSendResult>  $answers  one per call; the last one repeats
 */
function scriptedChannel(array $answers): void
{
    app()->instance(WhatsappChannel::class, new class($answers) implements WhatsappChannel
    {
        public int $calls = 0;

        /** @param list<WhatsappSendResult> $answers */
        public function __construct(private array $answers) {}

        public function state(bool $refresh = false): WhatsappState
        {
            return new WhatsappState(stage: WhatsappState::STAGE_ACTIVE, connection: 'ready');
        }

        public function request(int $requestedBy): WhatsappState
        {
            return $this->state();
        }

        public function connect(): WhatsappState
        {
            return $this->state();
        }

        public function acknowledgeRisk(int $userId): WhatsappState
        {
            return $this->state();
        }

        public function disconnect(): WhatsappState
        {
            return $this->state();
        }

        public function sendText(string $phone, string $text): WhatsappSendResult
        {
            $this->calls++;

            return count($this->answers) > 1 ? array_shift($this->answers) : $this->answers[0];
        }
    });
}

function runDelivery(Tenant $tenant, WhatsappMessage $message, int $attempt = 1, ?DeliverWhatsappMessage $job = null): DeliverWhatsappMessage
{
    $job ??= new DeliverWhatsappMessage($message->id);
    $job->withFakeQueueInteractions();
    $job->job->attempts = $attempt;

    inSchool($tenant, fn () => $job->handle(app(WhatsappChannel::class)));

    return $job;
}

it('puts a message back in the queue when it is the schools turn to wait, without counting a failure', function () {
    scriptedChannel([WhatsappSendResult::throttled(45, 'Menunggu giliran kirim.')]);
    $tenant = linkedSchool('wa-throttled');
    $message = inSchool($tenant, fn () => WhatsappMessage::factory()->create());

    // Far more waits than the old three tries allowed.
    foreach (range(1, 6) as $attempt) {
        runDelivery($tenant, $message, $attempt)->assertReleased(delay: 45);
    }

    $message->refresh();

    expect($message->status)->toBe(WhatsappMessageStatus::Pending)
        ->and($message->error_attempts)->toBe(0)
        ->and($message->error)->toBeNull();

    Http::assertNothingSent();
});

it('has no try limit and a deadline of twelve hours from when it was queued', function () {
    $job = new DeliverWhatsappMessage(1);

    expect($job->tries)->toBe(0)
        ->and($job->retryUntil()->getTimestamp())->toBe($job->queuedAt + 12 * 3600);

    $this->travel(5)->hours();

    // Asked again later, the deadline stays where it was.
    expect($job->retryUntil()->getTimestamp())->toBe($job->queuedAt + 12 * 3600);
});

it('closes a message as not sent when the wait would run past the deadline', function () {
    scriptedChannel([WhatsappSendResult::throttled(60, 'Menunggu giliran kirim.')]);
    $tenant = linkedSchool('wa-too-late');
    $message = inSchool($tenant, fn () => WhatsappMessage::factory()->create());
    $job = new DeliverWhatsappMessage($message->id);

    $this->travel(12)->hours();

    runDelivery($tenant, $message, 4, $job)->assertNotReleased();

    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Unsent)
        ->and($message->error)->toBe('Pengiriman tertunda terlalu lama.');
});

it('closes a message as not sent when the worker finds the deadline passed', function () {
    $tenant = linkedSchool('wa-worker-late');
    $message = inSchool($tenant, fn () => WhatsappMessage::factory()->create());
    $sent = inSchool($tenant, fn () => WhatsappMessage::factory()->sent()->create());

    inSchool($tenant, function () use ($message, $sent): void {
        (new DeliverWhatsappMessage($message->id))->failed(new MaxAttemptsExceededException('too long'));
        (new DeliverWhatsappMessage($sent->id))->failed(new MaxAttemptsExceededException('too long'));
    });

    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Unsent)
        ->and($message->error)->toBe('Pengiriman tertunda terlalu lama.')
        ->and($sent->refresh()->status)->toBe(WhatsappMessageStatus::Sent);
});

it('counts gateway failures on the message, between turns spent waiting', function () {
    scriptedChannel([
        WhatsappSendResult::failed('Gateway WhatsApp menjawab galat 502.', retryable: true),
        WhatsappSendResult::throttled(10, 'Menunggu giliran kirim.'),
        WhatsappSendResult::failed('Gateway WhatsApp menjawab galat 502.', retryable: true),
        WhatsappSendResult::throttled(10, 'Menunggu giliran kirim.'),
        WhatsappSendResult::failed('Gateway WhatsApp menjawab galat 502.', retryable: true),
    ]);
    $tenant = linkedSchool('wa-mixed');
    $message = inSchool($tenant, fn () => WhatsappMessage::factory()->create());

    runDelivery($tenant, $message, 1)->assertReleased(delay: 30);
    expect($message->refresh()->error_attempts)->toBe(1);

    runDelivery($tenant, $message, 2)->assertReleased(delay: 10);
    expect($message->refresh()->error_attempts)->toBe(1);

    runDelivery($tenant, $message, 3)->assertReleased(delay: 120);
    expect($message->refresh()->error_attempts)->toBe(2);

    runDelivery($tenant, $message, 4)->assertReleased(delay: 10);

    runDelivery($tenant, $message, 5)->assertNotReleased();
    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Failed)
        ->and($message->error_attempts)->toBe(3);
});

it('does not hang a message on the sync driver when it has to wait', function () {
    scriptedChannel([WhatsappSendResult::throttled(45, 'Menunggu giliran kirim.')]);
    $tenant = linkedSchool('wa-sync-wait');
    $message = inSchool($tenant, fn () => WhatsappMessage::factory()->create());

    inSchool($tenant, fn () => DeliverWhatsappMessage::dispatchSync($message->id));

    expect($message->refresh()->status)->toBe(WhatsappMessageStatus::Unsent)
        ->and($message->error)->toBe('Menunggu giliran kirim.');
});
