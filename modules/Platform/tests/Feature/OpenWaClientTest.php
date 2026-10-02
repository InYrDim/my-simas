<?php

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Infrastructure\Whatsapp\CredentialVault;
use Modules\Platform\App\Infrastructure\Whatsapp\OpenWaClient;
use Modules\Platform\App\Infrastructure\Whatsapp\OpenWaException;
use Modules\Platform\Database\Factories\WhatsappInstanceFactory;

/*
 * The OpenWA gateway client and the vault for the per-school session
 * keys. No request ever leaves the test: every answer is faked.
 */

const OPENWA_TEST_ADMIN_KEY = 'owa_k1_admin-key-for-tests';

const OPENWA_TEST_SESSION_KEY = 'owa_k1_session-key-for-tests';

const OPENWA_TEST_SESSION = '8f3c2b1a-9d4e-4c7a-8b2f-1e6d5a4c3b2a';

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'services.openwa.base_url' => 'https://wa.test/',
        'services.openwa.admin_api_key' => OPENWA_TEST_ADMIN_KEY,
        'services.openwa.credentials_key' => str_repeat('ab', 32),
    ]);
});

function openWa(): OpenWaClient
{
    return app(OpenWaClient::class);
}

it('creates a session with the admin key', function () {
    Http::fake(['wa.test/api/sessions' => Http::response(['id' => OPENWA_TEST_SESSION, 'name' => 'simas-x', 'status' => 'created'], 201)]);

    $session = openWa()->createSession('simas-x');

    expect($session['id'])->toBe(OPENWA_TEST_SESSION);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://wa.test/api/sessions'
        && $request->header('X-API-Key') === [OPENWA_TEST_ADMIN_KEY]
        && $request->data() === ['name' => 'simas-x']);
});

it('finds a session by its exact name', function () {
    Http::fake(['wa.test/api/sessions*' => Http::response([
        ['id' => 'other', 'name' => 'simas-x-2'],
        ['id' => OPENWA_TEST_SESSION, 'name' => 'simas-x'],
    ])]);

    expect(openWa()->findSessionByName('simas-x')['id'])->toBe(OPENWA_TEST_SESSION)
        ->and(openWa()->findSessionByName('simas-y'))->toBeNull();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://wa.test/api/sessions?name=simas-'));
});

it('mints an operator key scoped to the one session', function () {
    Http::fake(['wa.test/api/auth/api-keys' => Http::response(['id' => 'key-1', 'keyPrefix' => 'owa_k1_sess', 'apiKey' => OPENWA_TEST_SESSION_KEY], 201)]);

    expect(openWa()->createSessionKey('SIMAS simas-x', OPENWA_TEST_SESSION))
        ->toBe(['id' => 'key-1', 'apiKey' => OPENWA_TEST_SESSION_KEY]);

    Http::assertSent(fn (Request $request): bool => $request->header('X-API-Key') === [OPENWA_TEST_ADMIN_KEY]
        && $request->data() === ['name' => 'SIMAS simas-x', 'role' => 'operator', 'allowedSessions' => [OPENWA_TEST_SESSION]]);
});

it('refuses an answer without the key', function () {
    Http::fake(['wa.test/api/auth/api-keys' => Http::response(['id' => 'key-1'], 201)]);

    openWa()->createSessionKey('SIMAS simas-x', OPENWA_TEST_SESSION);
})->throws(OpenWaException::class);

it('revokes a key and deletes a session with the admin key', function () {
    Http::fake([
        'wa.test/api/auth/api-keys/key-1/revoke' => Http::response(['id' => 'key-1', 'isActive' => false]),
        'wa.test/api/sessions/'.OPENWA_TEST_SESSION => Http::response(null, 204),
    ]);

    openWa()->revokeKey('key-1');
    openWa()->deleteSession(OPENWA_TEST_SESSION);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://wa.test/api/auth/api-keys/key-1/revoke'
        && $request->header('X-API-Key') === [OPENWA_TEST_ADMIN_KEY]);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
        && $request->url() === 'https://wa.test/api/sessions/'.OPENWA_TEST_SESSION
        && $request->header('X-API-Key') === [OPENWA_TEST_ADMIN_KEY]);
});

it('drives a session with the session key, never the admin key', function (string $method, string $verb, string $path) {
    Http::fake(['wa.test/api/sessions/*' => Http::response(['id' => OPENWA_TEST_SESSION, 'status' => 'initializing'])]);

    openWa()->{$method}(OPENWA_TEST_SESSION, OPENWA_TEST_SESSION_KEY);

    Http::assertSent(fn (Request $request): bool => $request->method() === $verb
        && $request->url() === 'https://wa.test/api/sessions/'.OPENWA_TEST_SESSION.$path
        && $request->header('X-API-Key') === [OPENWA_TEST_SESSION_KEY]);
})->with([
    'start' => ['startSession', 'POST', '/start'],
    'stop' => ['stopSession', 'POST', '/stop'],
    'logout' => ['logoutSession', 'POST', '/logout'],
    'read' => ['session', 'GET', ''],
]);

it('returns the QR while there is one and null when the gateway has none', function () {
    Http::fake(['wa.test/api/sessions/*/qr' => Http::sequence()
        ->push(['qrCode' => 'data:image/png;base64,AAAA', 'status' => 'qr_ready'])
        ->push(['message' => 'Session is not in qr_ready'], 400)]);

    expect(openWa()->qr(OPENWA_TEST_SESSION, OPENWA_TEST_SESSION_KEY))->toBe('data:image/png;base64,AAAA')
        ->and(openWa()->qr(OPENWA_TEST_SESSION, OPENWA_TEST_SESSION_KEY))->toBeNull();
});

it('sends a text message through the session', function () {
    Http::fake(['wa.test/api/sessions/*/messages/send-text' => Http::response(['messageId' => 'true_628@c.us_3EB0', 'timestamp' => 1782813600], 201)]);

    expect(openWa()->sendText(OPENWA_TEST_SESSION, OPENWA_TEST_SESSION_KEY, '6281234567890@c.us', 'Halo'))
        ->toBe(['messageId' => 'true_628@c.us_3EB0']);

    Http::assertSent(fn (Request $request): bool => $request->header('X-API-Key') === [OPENWA_TEST_SESSION_KEY]
        && $request->data() === ['chatId' => '6281234567890@c.us', 'text' => 'Halo']);
});

it('turns a refusal into an exception that names the call and hides the key', function (int $status, bool $retryable) {
    Http::fake(['wa.test/*' => Http::response(['message' => 'key '.OPENWA_TEST_SESSION_KEY.' rejected'], $status)]);

    try {
        openWa()->startSession(OPENWA_TEST_SESSION, OPENWA_TEST_SESSION_KEY);
        $this->fail('The call should have thrown.');
    } catch (OpenWaException $exception) {
        expect($exception->status)->toBe($status)
            ->and($exception->retryable())->toBe($retryable)
            ->and($exception->getMessage())->toContain("{$status}")
            ->and($exception->getMessage())->not->toContain('owa_k1_')
            ->and($exception->getTraceAsString())->not->toContain(OPENWA_TEST_SESSION_KEY);
    }
})->with([
    'unauthorized' => [401, false],
    'conflict' => [409, false],
    'rate limited' => [429, true],
    'gateway down' => [502, true],
]);

it('reports an unreachable gateway as retryable', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out'));

    try {
        openWa()->session(OPENWA_TEST_SESSION, OPENWA_TEST_SESSION_KEY);
        $this->fail('The call should have thrown.');
    } catch (OpenWaException $exception) {
        expect($exception->status)->toBeNull()
            ->and($exception->retryable())->toBeTrue();
    }
});

it('refuses to call without a base URL or an admin key', function (string $missing) {
    config([$missing => null]);

    openWa()->createSession('simas-x');
})->with(['services.openwa.base_url', 'services.openwa.admin_api_key'])->throws(OpenWaException::class);

it('stores the session key encrypted with the credentials key, not the application key', function () {
    $instance = WhatsappInstanceFactory::new()->active()->create(['api_key' => OPENWA_TEST_SESSION_KEY]);

    $stored = DB::table('whatsapp_instances')->where('id', $instance->id)->value('api_key');

    expect($stored)->not->toContain('owa_k1_')
        ->and($instance->fresh()->api_key)->toBe(OPENWA_TEST_SESSION_KEY)
        ->and(fn () => decrypt($stored, false))->toThrow(Exception::class);
});

it('never puts the session key in an array or JSON form of the instance', function () {
    $instance = WhatsappInstanceFactory::new()->active()->create(['api_key' => OPENWA_TEST_SESSION_KEY]);

    expect($instance->fresh()->toArray())->not->toHaveKey('api_key')->not->toHaveKey('api_key_id')
        ->and($instance->fresh()->toJson())->not->toContain('owa_k1_');
});

it('cannot open a stored key after the credentials key changed', function () {
    $instance = WhatsappInstanceFactory::new()->active()->create(['api_key' => OPENWA_TEST_SESSION_KEY]);

    config(['services.openwa.credentials_key' => str_repeat('cd', 32)]);

    $instance->fresh()->api_key;
})->throws(OpenWaException::class);

it('stores no key without a usable credentials key', function (?string $key) {
    config(['services.openwa.credentials_key' => $key]);

    expect(app(CredentialVault::class)->ready())->toBeFalse();

    WhatsappInstanceFactory::new()->active()->create();
})->with([
    'unset' => [null],
    'too short' => ['abcd'],
    'not hex' => [str_repeat('zz', 32)],
])->throws(OpenWaException::class);

it('keeps one instance per school', function () {
    $instance = WhatsappInstanceFactory::new()->create();

    WhatsappInstance::query()->create(['tenant_id' => $instance->tenant_id, 'status' => 'pending']);
})->throws(UniqueConstraintViolationException::class);

it('reads and writes a provider setting', function () {
    expect(ProviderSetting::read(ProviderSetting::WHATSAPP_AUTO_APPROVE, false))->toBeFalse();

    ProviderSetting::write(ProviderSetting::WHATSAPP_AUTO_APPROVE, true);
    ProviderSetting::write(ProviderSetting::WHATSAPP_AUTO_APPROVE, true);

    expect(ProviderSetting::read(ProviderSetting::WHATSAPP_AUTO_APPROVE, false))->toBeTrue()
        ->and(ProviderSetting::query()->count())->toBe(1);
});
