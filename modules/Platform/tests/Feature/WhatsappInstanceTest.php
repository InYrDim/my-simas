<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Platform\App\Contracts\DTOs\WhatsappState;
use Modules\Platform\App\Contracts\Exceptions\TenantNotSetException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\Tenant;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;
use Modules\Platform\Database\Factories\ProviderUserFactory;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Database\Factories\WhatsappInstanceFactory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/*
 * A school's WhatsApp request and the provider's decision (fase 9, stage
 * 1). The gateway is faked: no request leaves the test.
 */

const WA_TEST_SESSION = '5b1f0c1e-7a2d-4f6b-9c3e-2d8a6b4c1f00';

const WA_TEST_KEY = 'owa_k1_school-session-key-for-tests';

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        // With a Vite dev server running, a full page load asks it to render
        // (SSR) over HTTP, which the stray-request guard would refuse.
        'inertia.ssr.enabled' => false,
        'services.openwa.base_url' => 'https://wa.test',
        'services.openwa.admin_api_key' => 'owa_k1_admin-key-for-tests',
        'services.openwa.credentials_key' => str_repeat('ab', 32),
    ]);
});

function waProvider(): ProviderUser
{
    /** @var ProviderUser $provider */
    $provider = ProviderUserFactory::new()->create();

    actingAs($provider, 'provider');

    return $provider;
}

function waConsole(string $path = ''): string
{
    return 'http://console.localhost/whatsapp'.$path;
}

/**
 * The school's side of the contract, inside its tenant context.
 *
 * @template T
 *
 * @param  callable(WhatsappChannel): T  $callback
 * @return T
 */
function waChannel(Tenant $tenant, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant->id, fn () => $callback(app(WhatsappChannel::class)));
}

/**
 * The gateway accepts a new session and mints its key.
 */
function waGatewayApproves(): void
{
    Http::fake([
        'wa.test/api/sessions' => Http::response(['id' => WA_TEST_SESSION, 'status' => 'created'], 201),
        'wa.test/api/auth/api-keys' => Http::response(['id' => 'key-1', 'apiKey' => WA_TEST_KEY], 201),
    ]);
}

it('reports no WhatsApp for a school that never asked', function () {
    $tenant = TenantFactory::new()->create();

    $state = waChannel($tenant, fn (WhatsappChannel $channel) => $channel->state());

    expect($state->stage)->toBe(WhatsappState::STAGE_NONE)
        ->and($state->connected())->toBeFalse();
});

it('records a request as pending without calling the gateway', function () {
    $tenant = TenantFactory::new()->create();

    $state = waChannel($tenant, fn (WhatsappChannel $channel) => $channel->request(41));

    $instance = WhatsappInstance::query()->where('tenant_id', $tenant->id)->sole();

    expect($state->stage)->toBe(WhatsappState::STAGE_PENDING)
        ->and($state->requestedAt)->not->toBeNull()
        ->and($instance->status)->toBe(WhatsappInstanceStatus::Pending)
        ->and($instance->requested_by)->toBe(41)
        ->and($instance->session_id)->toBeNull();

    Http::assertNothingSent();
});

it('keeps one request when a school asks twice', function () {
    $tenant = TenantFactory::new()->create();

    waChannel($tenant, fn (WhatsappChannel $channel) => $channel->request(41));
    $state = waChannel($tenant, fn (WhatsappChannel $channel) => $channel->request(42));

    expect($state->stage)->toBe(WhatsappState::STAGE_PENDING)
        ->and(WhatsappInstance::query()->count())->toBe(1)
        ->and(WhatsappInstance::query()->sole()->requested_by)->toBe(41);
});

it('answers only for the school in context', function () {
    $asking = TenantFactory::new()->create();
    $other = TenantFactory::new()->create();

    waChannel($asking, fn (WhatsappChannel $channel) => $channel->request(41));

    expect(waChannel($other, fn (WhatsappChannel $channel) => $channel->state())->stage)->toBe(WhatsappState::STAGE_NONE)
        ->and(waChannel($asking, fn (WhatsappChannel $channel) => $channel->state())->stage)->toBe(WhatsappState::STAGE_PENDING);
});

it('fails closed without a school in context', function (string $method) {
    $method === 'state'
        ? app(WhatsappChannel::class)->state()
        : app(WhatsappChannel::class)->request(41);
})->with(['state', 'request'])->throws(TenantNotSetException::class);

it('approves a request: a session and a key scoped to it, stored for the school', function () {
    waGatewayApproves();
    $provider = waProvider();
    $instance = WhatsappInstanceFactory::new()->create();
    $name = 'simas-'.Str::lower($instance->tenant_id);

    post(waConsole("/{$instance->id}/approve"))->assertRedirect()->assertSessionHasNoErrors();

    $instance->refresh();

    expect($instance->status)->toBe(WhatsappInstanceStatus::Active)
        ->and($instance->decided_by)->toBe($provider->id)
        ->and($instance->session_id)->toBe(WA_TEST_SESSION)
        ->and($instance->session_name)->toBe($name)
        ->and($instance->api_key)->toBe(WA_TEST_KEY)
        ->and($instance->api_key_id)->toBe('key-1')
        ->and($instance->connection_status)->toBe('created')
        ->and($instance->usable())->toBeTrue()
        ->and(DB::table('whatsapp_instances')->where('id', $instance->id)->value('api_key'))->not->toContain('owa_k1_');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://wa.test/api/sessions'
        && $request->data() === ['name' => $name]);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://wa.test/api/auth/api-keys'
        && $request['role'] === 'operator'
        && $request['allowedSessions'] === [WA_TEST_SESSION]);
});

it('uses the session already registered under the schools name', function () {
    $instance = WhatsappInstanceFactory::new()->create();
    $name = 'simas-'.Str::lower($instance->tenant_id);

    Http::fake(function (Request $request) use ($name) {
        return match (true) {
            $request->method() === 'POST' && str_ends_with($request->url(), '/api/sessions') => Http::response(['message' => 'Session name taken'], 409),
            $request->method() === 'GET' => Http::response([['id' => WA_TEST_SESSION, 'name' => $name, 'status' => 'disconnected']]),
            default => Http::response(['id' => 'key-2', 'apiKey' => WA_TEST_KEY], 201),
        };
    });

    waProvider();

    post(waConsole("/{$instance->id}/approve"))->assertSessionHasNoErrors();

    expect($instance->refresh()->status)->toBe(WhatsappInstanceStatus::Active)
        ->and($instance->session_id)->toBe(WA_TEST_SESSION)
        ->and($instance->connection_status)->toBe('disconnected');
});

it('leaves the request pending when the gateway refuses', function () {
    Http::fake(['wa.test/*' => Http::response(['message' => 'boom'], 502)]);
    waProvider();
    $instance = WhatsappInstanceFactory::new()->create();

    post(waConsole("/{$instance->id}/approve"))->assertSessionHasErrors('whatsapp');

    expect($instance->refresh()->status)->toBe(WhatsappInstanceStatus::Pending)
        ->and($instance->session_id)->toBeNull()
        ->and($instance->getRawOriginal('api_key'))->toBeNull()
        ->and($instance->last_error)->toContain('502');
});

it('mints no key when it could not be stored encrypted', function () {
    config(['services.openwa.credentials_key' => null]);
    waProvider();
    $instance = WhatsappInstanceFactory::new()->create();

    post(waConsole("/{$instance->id}/approve"))->assertSessionHasErrors('whatsapp');

    expect($instance->refresh()->status)->toBe(WhatsappInstanceStatus::Pending);

    Http::assertNothingSent();
});

it('rejects with a note the school can read, and takes the request back when asked again', function () {
    waProvider();
    $tenant = TenantFactory::new()->create();
    $instance = WhatsappInstanceFactory::new()->forTenant($tenant->id)->create();

    post(waConsole("/{$instance->id}/reject"), ['note' => 'Lengkapi profil sekolah dulu.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $state = waChannel($tenant, fn (WhatsappChannel $channel) => $channel->state());

    expect($state->stage)->toBe(WhatsappState::STAGE_REJECTED)
        ->and($state->note)->toBe('Lengkapi profil sekolah dulu.');

    $again = waChannel($tenant, fn (WhatsappChannel $channel) => $channel->request(77));

    expect($again->stage)->toBe(WhatsappState::STAGE_PENDING)
        ->and($again->note)->toBeNull()
        ->and($instance->refresh()->requested_by)->toBe(77)
        ->and($instance->decided_at)->toBeNull()
        ->and(WhatsappInstance::query()->count())->toBe(1);

    Http::assertNothingSent();
});

it('decides a request once', function (string $action) {
    waProvider();
    $instance = WhatsappInstanceFactory::new()->rejected('Sudah ditolak.')->create();

    post(waConsole("/{$instance->id}/{$action}"))->assertSessionHasErrors('whatsapp');

    expect($instance->refresh()->status)->toBe(WhatsappInstanceStatus::Rejected)
        ->and($instance->note)->toBe('Sudah ditolak.');

    Http::assertNothingSent();
})->with(['approve', 'reject']);

it('approves a new request by itself when the provider turned that on', function () {
    waGatewayApproves();
    waProvider();

    put(waConsole('/settings'), ['auto_approve' => true])->assertRedirect()->assertSessionHasNoErrors();

    expect(ProviderSetting::read(ProviderSetting::WHATSAPP_AUTO_APPROVE))->toBeTrue();

    $tenant = TenantFactory::new()->create();
    $state = waChannel($tenant, fn (WhatsappChannel $channel) => $channel->request(41));
    $instance = WhatsappInstance::query()->where('tenant_id', $tenant->id)->sole();

    expect($state->stage)->toBe(WhatsappState::STAGE_ACTIVE)
        ->and($state->connection)->toBe('created')
        ->and($instance->decided_by)->toBeNull()
        ->and($instance->api_key)->toBe(WA_TEST_KEY);
});

it('lets an automatic approval that fails wait for the provider', function () {
    Http::fake(['wa.test/*' => Http::response(null, 503)]);
    ProviderSetting::write(ProviderSetting::WHATSAPP_AUTO_APPROVE, true);

    $tenant = TenantFactory::new()->create();
    $state = waChannel($tenant, fn (WhatsappChannel $channel) => $channel->request(41));

    expect($state->stage)->toBe(WhatsappState::STAGE_PENDING)
        ->and($state->lastError)->toBeNull()
        ->and(WhatsappInstance::query()->sole()->last_error)->toContain('503');
});

it('turns automatic approval off again', function () {
    ProviderSetting::write(ProviderSetting::WHATSAPP_AUTO_APPROVE, true);
    waProvider();

    put(waConsole('/settings'), ['auto_approve' => false])->assertSessionHasNoErrors();
    put(waConsole('/settings'), [])->assertSessionHasErrors('auto_approve');

    $tenant = TenantFactory::new()->create();

    expect(waChannel($tenant, fn (WhatsappChannel $channel) => $channel->request(41))->stage)->toBe(WhatsappState::STAGE_PENDING);

    Http::assertNothingSent();
});

it('lists the requests with their schools, waiting ones first, and never a key', function () {
    waProvider();

    $linked = TenantFactory::new()->create(['name' => 'SMA Terhubung']);
    $waiting = TenantFactory::new()->create(['name' => 'SMA Menunggu']);

    WhatsappInstanceFactory::new()->forTenant($linked->id)->connected('628111000111')->create([
        'api_key' => WA_TEST_KEY,
        'requested_at' => now()->subDays(3),
    ]);
    WhatsappInstanceFactory::new()->forTenant($waiting->id)->create();

    $response = get(waConsole())->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Platform/Whatsapp/Index')
        ->where('autoApprove', false)
        ->where('gatewayReady', true)
        ->has('instances', 2)
        ->where('instances.0.tenantName', 'SMA Menunggu')
        ->where('instances.0.status', 'pending')
        ->where('instances.1.tenantName', 'SMA Terhubung')
        ->where('instances.1.status', 'active')
        ->where('instances.1.connection', 'ready')
        ->where('instances.1.phone', '628111000111')
        ->missing('instances.1.apiKey')
        ->missing('instances.1.api_key')
        ->missing('instances.1.apiKeyId')
        ->missing('instances.1.sessionId')
    );

    expect($response->getContent())->not->toContain('owa_k1_');
});

it('warns when the gateway is not configured', function (string $missing) {
    config([$missing => null]);
    waProvider();

    get(waConsole())->assertInertia(fn (Assert $page) => $page->where('gatewayReady', false));
})->with([
    'services.openwa.base_url',
    'services.openwa.admin_api_key',
    'services.openwa.credentials_key',
]);

it('keeps the console page and its actions behind the provider guard', function () {
    $instance = WhatsappInstanceFactory::new()->create();

    get(waConsole())->assertRedirect(route('platform.login'));
    post(waConsole("/{$instance->id}/approve"))->assertRedirect(route('platform.login'));
    post(waConsole("/{$instance->id}/reject"))->assertRedirect(route('platform.login'));
    put(waConsole('/settings'), ['auto_approve' => true])->assertRedirect(route('platform.login'));

    expect($instance->refresh()->status)->toBe(WhatsappInstanceStatus::Pending)
        ->and(ProviderSetting::read(ProviderSetting::WHATSAPP_AUTO_APPROVE, false))->toBeFalse();

    Http::assertNothingSent();
});
