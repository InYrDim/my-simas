<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Platform\App\Contracts\DTOs\WhatsappState;
use Modules\Platform\App\Contracts\Exceptions\WhatsappUnavailableException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Database\Factories\WhatsappInstanceFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/*
 * Linking a school's number (fase 9, stage 2): starting the session, the
 * QR, the states the gateway reports, unlinking, and the provider
 * switching a school off and on. The gateway is faked throughout.
 */

const WA_LINK_KEY = 'owa_k1_link-session-key-for-tests';

const WA_LINK_QR = 'data:image/png;base64,QRQRQR';

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.openwa.base_url' => 'https://wa.test',
        'services.openwa.admin_api_key' => 'owa_k1_admin-key-for-tests',
        'services.openwa.credentials_key' => str_repeat('ab', 32),
    ]);
});

/**
 * An approved school with a session that is not linked yet.
 *
 * @param  array<string, mixed>  $attributes
 */
function waApproved(array $attributes = []): WhatsappInstance
{
    return WhatsappInstanceFactory::new()->active()->create(['api_key' => WA_LINK_KEY, ...$attributes]);
}

/**
 * @template T
 *
 * @param  callable(WhatsappChannel): T  $callback
 * @return T
 */
function waLink(WhatsappInstance $instance, callable $callback): mixed
{
    return app(TenantContext::class)->run($instance->tenant_id, fn () => $callback(app(WhatsappChannel::class)));
}

/**
 * The gateway reports the session in these states, one per read, and
 * accepts every other call. (One closure: with a URL map, every stub is
 * invoked for every request, which would use up the states.)
 *
 * @param  list<array<string, mixed>>  $reads
 */
function waGatewaySays(array $reads, ?string $qrCode = WA_LINK_QR): void
{
    Http::fake(function (Request $request) use (&$reads, $qrCode) {
        $url = $request->url();

        if (str_ends_with($url, '/qr')) {
            return $qrCode === null
                ? Http::response(['message' => 'Session is not in qr_ready'], 400)
                : Http::response(['qrCode' => $qrCode, 'status' => 'qr_ready']);
        }

        if (str_ends_with($url, '/start')) {
            return Http::response(['status' => 'initializing']);
        }

        if (str_ends_with($url, '/stop') || str_ends_with($url, '/logout')) {
            return Http::response(['status' => 'disconnected']);
        }

        if ($reads === []) {
            throw new LogicException('The session was read, but the test expected no read.');
        }

        // One state per read; the last one stays.
        return Http::response(count($reads) > 1 ? array_shift($reads) : $reads[0]);
    });
}

function waCalls(string $suffix): int
{
    return Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), $suffix))->count();
}

it('starts the session once, however often connect is pressed', function () {
    waGatewaySays([['status' => 'created'], ['status' => 'initializing'], ['status' => 'qr_ready']]);
    $instance = waApproved();

    $first = waLink($instance, fn (WhatsappChannel $channel) => $channel->connect());
    waLink($instance, fn (WhatsappChannel $channel) => $channel->connect());
    waLink($instance, fn (WhatsappChannel $channel) => $channel->connect());

    expect($first->connection)->toBe('initializing')
        ->and($first->lastError)->toBeNull()
        ->and(waCalls('/start'))->toBe(1)
        ->and($instance->refresh()->connection_status)->toBe('qr_ready');

    Http::assertSent(fn (Request $request): bool => $request->url() === "https://wa.test/api/sessions/{$instance->session_id}/start"
        && $request->header('X-API-Key') === [WA_LINK_KEY]);
});

it('takes a session the gateway says is already running as started', function () {
    Http::fake([
        'wa.test/api/sessions/*/start' => Http::response(['message' => 'Session already started'], 400),
        'wa.test/api/sessions/*' => Http::response(['status' => 'disconnected']),
    ]);
    $instance = waApproved();

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->connect());

    expect($state->connection)->toBe('initializing')
        ->and($state->lastError)->toBeNull();
});

it('hands over the QR while the gateway shows one, with the schools own key', function () {
    waGatewaySays([['status' => 'qr_ready']]);
    $instance = waApproved(['connection_status' => 'initializing']);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->state(refresh: true));

    expect($state->connection)->toBe('qr_ready')
        ->and($state->qrCode)->toBe(WA_LINK_QR)
        ->and($state->connected())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/qr')
        && $request->header('X-API-Key') === [WA_LINK_KEY]);
});

it('answers from what was last seen unless asked to refresh', function () {
    $instance = waApproved(['connection_status' => 'qr_ready']);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->state());

    expect($state->connection)->toBe('qr_ready')
        ->and($state->qrCode)->toBeNull();

    Http::assertNothingSent();
});

it('records the linked number once the session is ready', function () {
    waGatewaySays([['status' => 'ready', 'phone' => '628111000111', 'pushName' => 'TU SMA Uji']]);
    $instance = waApproved(['connection_status' => 'authenticating']);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->state(refresh: true));

    expect($state->connected())->toBeTrue()
        ->and($state->phone)->toBe('628111000111')
        ->and($state->pushName)->toBe('TU SMA Uji')
        ->and($state->qrCode)->toBeNull()
        ->and($instance->refresh()->connected_at)->not->toBeNull()
        ->and(waCalls('/qr'))->toBe(0);
});

it('does not start a session that is already linked', function () {
    waGatewaySays([['status' => 'ready', 'phone' => '628111000111']]);
    $instance = waApproved();

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->connect());

    expect($state->connected())->toBeTrue()
        ->and(waCalls('/start'))->toBe(0);
});

it('passes on why the gateway could not link', function () {
    waGatewaySays([['status' => 'failed', 'lastError' => 'Authentication timed out']]);
    $instance = waApproved(['connection_status' => 'qr_ready']);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->state(refresh: true));

    expect($state->connection)->toBe('failed')
        ->and($state->lastError)->toBe('Authentication timed out')
        ->and($state->qrCode)->toBeNull();
});

it('forgets the number when the gateway reports the session lost', function () {
    waGatewaySays([['status' => 'disconnected']]);
    $instance = WhatsappInstanceFactory::new()->connected('628111000111')->create(['api_key' => WA_LINK_KEY]);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->state(refresh: true));

    expect($state->connected())->toBeFalse()
        ->and($state->phone)->toBeNull()
        ->and($instance->refresh()->connected_at)->toBeNull();
});

it('keeps what it knew and says so plainly when the gateway is down', function (callable $down, string $expected) {
    Http::fake($down);
    $instance = WhatsappInstanceFactory::new()->connected('628111000111')->create(['api_key' => WA_LINK_KEY]);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->state(refresh: true));

    expect($state->connection)->toBe('ready')
        ->and($state->phone)->toBe('628111000111')
        ->and($state->lastError)->toContain($expected)
        ->and($state->lastError)->not->toContain($instance->session_id)
        ->and($state->lastError)->not->toContain('/api/');
})->with([
    'unreachable' => [fn () => throw new ConnectionException('cURL error 7'), 'tidak bisa dihubungi'],
    'server error' => [fn () => Http::response(null, 502), '502'],
]);

it('reports a failed start without throwing', function () {
    Http::fake([
        'wa.test/api/sessions/*/start' => Http::response(null, 500),
        'wa.test/api/sessions/*' => Http::response(['status' => 'created']),
    ]);
    $instance = waApproved();

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->connect());

    expect($state->connection)->toBe('created')
        ->and($state->lastError)->toContain('500');
});

it('unlinks the number and stops the session', function () {
    waGatewaySays([]);
    $instance = WhatsappInstanceFactory::new()->connected('628111000111')->create(['api_key' => WA_LINK_KEY]);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->disconnect());

    expect($state->stage)->toBe(WhatsappState::STAGE_ACTIVE)
        ->and($state->connection)->toBe('disconnected')
        ->and($state->phone)->toBeNull()
        ->and($instance->refresh()->connected_at)->toBeNull()
        ->and(waCalls('/logout'))->toBe(1)
        ->and(waCalls('/stop'))->toBe(1);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/logout')
        && $request->header('X-API-Key') === [WA_LINK_KEY]);
});

it('unlinks a session that was never started', function () {
    Http::fake([
        'wa.test/api/sessions/*/logout' => Http::response(['message' => 'Session is not started'], 400),
        'wa.test/api/sessions/*/stop' => Http::response(['status' => 'disconnected']),
    ]);
    $instance = waApproved(['connection_status' => 'qr_ready']);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->disconnect());

    expect($state->connection)->toBe('disconnected')
        ->and($state->lastError)->toBeNull();
});

it('stays linked when the gateway refuses to unlink', function () {
    Http::fake(['wa.test/*' => Http::response(null, 503)]);
    $instance = WhatsappInstanceFactory::new()->connected('628111000111')->create(['api_key' => WA_LINK_KEY]);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->disconnect());

    expect($state->connected())->toBeTrue()
        ->and($state->lastError)->toContain('503');
});

it('refuses to link or unlink a school without usable WhatsApp', function (string $factoryState, string $method) {
    $instance = WhatsappInstanceFactory::new()->{$factoryState}()->create();

    try {
        waLink($instance, fn (WhatsappChannel $channel) => $channel->{$method}());
        $this->fail('The call should have been refused.');
    } catch (WhatsappUnavailableException) {
        Http::assertNothingSent();
    }
})->with([
    'pending' => ['pending', 'connect'],
    'rejected' => ['rejected', 'connect'],
    'disabled' => ['disabled', 'connect'],
    'disabled, unlink' => ['disabled', 'disconnect'],
]);

it('refuses to link a school that never asked', function () {
    $tenant = TenantFactory::new()->create();

    app(TenantContext::class)->run($tenant->id, fn () => app(WhatsappChannel::class)->connect());
})->throws(WhatsappUnavailableException::class);

it('does not ask the gateway about a school that is not approved', function () {
    $instance = WhatsappInstanceFactory::new()->disabled('Tagihan menunggak.')->create();

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->state(refresh: true));

    expect($state->stage)->toBe(WhatsappState::STAGE_DISABLED)
        ->and($state->note)->toBe('Tagihan menunggak.')
        ->and($state->connection)->toBeNull();

    Http::assertNothingSent();
});

it('lets the provider switch a school off: the session stops and the school is told why', function () {
    waGatewaySays([]);
    $provider = ProviderUserFactory::new()->create();
    actingAs($provider, 'provider');
    $instance = WhatsappInstanceFactory::new()->connected('628111000111')->create(['api_key' => WA_LINK_KEY]);

    post("http://console.localhost/whatsapp/{$instance->id}/disable", ['note' => 'Tagihan menunggak.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $instance->refresh();

    expect($instance->status)->toBe(WhatsappInstanceStatus::Disabled)
        ->and($instance->decided_by)->toBe($provider->id)
        ->and($instance->connection_status)->toBe('disconnected')
        ->and($instance->getRawOriginal('api_key'))->not->toBeNull()
        ->and(waCalls('/stop'))->toBe(1)
        ->and(waCalls('/logout'))->toBe(0);

    $state = waLink($instance, fn (WhatsappChannel $channel) => $channel->state(refresh: true));

    expect($state->stage)->toBe(WhatsappState::STAGE_DISABLED)
        ->and($state->note)->toBe('Tagihan menunggak.');
});

it('switches a school off even when the gateway does not answer', function () {
    Http::fake(['wa.test/*' => Http::response(null, 502)]);
    actingAs(ProviderUserFactory::new()->create(), 'provider');
    $instance = WhatsappInstanceFactory::new()->connected()->create(['api_key' => WA_LINK_KEY]);

    post("http://console.localhost/whatsapp/{$instance->id}/disable")->assertSessionHasErrors('whatsapp');

    expect($instance->refresh()->status)->toBe(WhatsappInstanceStatus::Disabled)
        ->and($instance->usable())->toBeFalse();
});

it('lets the provider switch a school back on without touching the gateway', function () {
    actingAs(ProviderUserFactory::new()->create(), 'provider');
    $instance = WhatsappInstanceFactory::new()->disabled('Tagihan menunggak.')->create();

    post("http://console.localhost/whatsapp/{$instance->id}/enable")
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($instance->refresh()->status)->toBe(WhatsappInstanceStatus::Active)
        ->and($instance->note)->toBeNull()
        ->and($instance->usable())->toBeTrue();

    Http::assertNothingSent();
});

it('switches off only what is active and on only what is disabled', function (string $factoryState, string $action, WhatsappInstanceStatus $stays) {
    actingAs(ProviderUserFactory::new()->create(), 'provider');
    $instance = WhatsappInstanceFactory::new()->{$factoryState}()->create();

    post("http://console.localhost/whatsapp/{$instance->id}/{$action}")->assertSessionHasErrors('whatsapp');

    expect($instance->refresh()->status)->toBe($stays);

    Http::assertNothingSent();
})->with([
    'disable a pending request' => ['pending', 'disable', WhatsappInstanceStatus::Pending],
    'disable twice' => ['disabled', 'disable', WhatsappInstanceStatus::Disabled],
    'enable an active one' => ['active', 'enable', WhatsappInstanceStatus::Active],
    'enable a rejected one' => ['rejected', 'enable', WhatsappInstanceStatus::Rejected],
]);

it('keeps switching off and on behind the provider guard', function () {
    $active = waApproved();
    $disabled = WhatsappInstanceFactory::new()->disabled()->create();

    post("http://console.localhost/whatsapp/{$active->id}/disable")->assertRedirect(route('platform.login'));
    post("http://console.localhost/whatsapp/{$disabled->id}/enable")->assertRedirect(route('platform.login'));

    expect($active->refresh()->status)->toBe(WhatsappInstanceStatus::Active)
        ->and($disabled->refresh()->status)->toBe(WhatsappInstanceStatus::Disabled);

    Http::assertNothingSent();
});

it('sends a text from the linked number and reports what the gateway said', function () {
    Http::fake(['wa.test/api/sessions/*/messages/send-text' => Http::response(['messageId' => 'msg-1'], 201)]);
    $instance = WhatsappInstanceFactory::new()->connected()->create(['api_key' => WA_LINK_KEY]);

    $result = waLink($instance, fn (WhatsappChannel $channel) => $channel->sendText('6281234567890', 'Halo'));

    expect($result->sent)->toBeTrue()
        ->and($result->messageId)->toBe('msg-1')
        ->and($result->error)->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->header('X-API-Key') === [WA_LINK_KEY]
        && $request->data() === ['chatId' => '6281234567890@c.us', 'text' => 'Halo']);
});

it('sends nothing to a number that is not digits in international format', function (string $phone) {
    $instance = WhatsappInstanceFactory::new()->connected()->create(['api_key' => WA_LINK_KEY]);

    $result = waLink($instance, fn (WhatsappChannel $channel) => $channel->sendText($phone, 'Halo'));

    expect($result->sent)->toBeFalse()
        ->and($result->retryable)->toBeFalse()
        ->and($result->unavailable)->toBeFalse();

    Http::assertNothingSent();
})->with(['0812-3456-7890', '+6281234567890', '123', '6281234567890@c.us']);

it('sends nothing for a school without linked WhatsApp', function (string $factoryState) {
    $instance = WhatsappInstanceFactory::new()->{$factoryState}()->create();

    $result = waLink($instance, fn (WhatsappChannel $channel) => $channel->sendText('6281234567890', 'Halo'));

    expect($result->sent)->toBeFalse()
        ->and($result->unavailable)->toBeTrue();

    Http::assertNothingSent();
})->with(['pending', 'active', 'disabled']);

it('says whether a refused message is worth another try', function (int $status, bool $retryable) {
    Http::fake(['wa.test/*' => Http::response(null, $status)]);
    $instance = WhatsappInstanceFactory::new()->connected()->create(['api_key' => WA_LINK_KEY]);

    $result = waLink($instance, fn (WhatsappChannel $channel) => $channel->sendText('6281234567890', 'Halo'));

    expect($result->sent)->toBeFalse()
        ->and($result->retryable)->toBe($retryable)
        ->and($result->error)->toContain((string) $status)
        ->and($result->error)->not->toContain($instance->session_id);
})->with([[422, false], [429, true], [503, true]]);
