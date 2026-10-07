<?php

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Platform\App\Contracts\DTOs\WhatsappSendResult;
use Modules\Platform\App\Contracts\TenantCache;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Database\Factories\WhatsappInstanceFactory;

/*
 * Safe sending (fase 16, stage 1A): the pause between messages, the daily
 * limit and the circuit breaker, each per school. The gateway is faked.
 */

const WA_GUARD_KEY = 'owa_k1_guard-session-key-for-tests';

beforeEach(function () {
    Http::preventStrayRequests();
    Http::fake(function () {
        $status = $GLOBALS['waGuardStatus'];

        if ($status === null) {
            throw new ConnectionException('cURL error 7');
        }

        return Http::response($status === 201 ? ['messageId' => 'msg-1'] : null, $status);
    });
    waGuardGatewayAnswers();

    $this->travelTo('2026-03-30 10:00:00');

    config([
        'services.openwa.base_url' => 'https://wa.test',
        'services.openwa.admin_api_key' => 'owa_k1_admin-key-for-tests',
        'services.openwa.credentials_key' => str_repeat('ab', 32),
        'services.openwa.pace_min' => 5,
        'services.openwa.pace_max' => 5,
        'services.openwa.daily_limit' => 500,
        'services.openwa.breaker_threshold' => 3,
        'services.openwa.breaker_pause' => 300,
    ]);
});

function waGuardSchool(): WhatsappInstance
{
    return WhatsappInstanceFactory::new()->connected()->create(['api_key' => WA_GUARD_KEY]);
}

function waGuardNoPause(): void
{
    config(['services.openwa.pace_min' => 0, 'services.openwa.pace_max' => 0]);
}

function waGuardSend(WhatsappInstance $instance, string $phone = '6281234567890'): WhatsappSendResult
{
    return app(TenantContext::class)->run(
        $instance->tenant_id,
        fn () => app(WhatsappChannel::class)->sendText($phone, 'Halo'),
    );
}

/**
 * @return array{until: string|null, reason: string|null}
 */
function waGuardPause(WhatsappInstance $instance): array
{
    $state = app(TenantContext::class)->run($instance->tenant_id, fn () => app(WhatsappChannel::class)->state());

    return ['until' => $state->pausedUntil, 'reason' => $state->pauseReason];
}

function waGuardSends(): int
{
    return Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/send-text'))->count();
}

/**
 * What the faked gateway answers right now: a status code, or null for
 * "unreachable". One closure is registered once, because with several
 * Http::fake calls the first stub that matches keeps winning.
 */
function waGuardGatewayAnswers(): void
{
    $GLOBALS['waGuardStatus'] = 201;
}

function waGuardGatewayDown(?int $status = 503): void
{
    $GLOBALS['waGuardStatus'] = $status;
}

it('lets the first message go and holds the next until the pause has passed', function () {
    $instance = waGuardSchool();

    expect(waGuardSend($instance)->sent)->toBeTrue();

    $held = waGuardSend($instance);

    expect($held->sent)->toBeFalse()
        ->and($held->throttled)->toBeTrue()
        ->and($held->retryable)->toBeTrue()
        ->and($held->retryAfter)->toBe(5)
        ->and(waGuardSends())->toBe(1);

    $this->travel(2)->seconds();

    expect(waGuardSend($instance)->retryAfter)->toBe(3);

    $this->travel(3)->seconds();

    expect(waGuardSend($instance)->sent)->toBeTrue()
        ->and(waGuardSends())->toBe(2);
});

it('draws the pause from the configured range', function () {
    config(['services.openwa.pace_min' => 3, 'services.openwa.pace_max' => 10]);
    $instance = waGuardSchool();

    waGuardSend($instance);

    expect(waGuardSend($instance)->retryAfter)->toBeBetween(3, 10);
});

it('does not use up a pacing slot on a number that is not valid', function () {
    $instance = waGuardSchool();

    $bad = waGuardSend($instance, '0812');

    expect($bad->throttled)->toBeFalse()
        ->and(waGuardSend($instance)->sent)->toBeTrue();
});

it('holds a school at its daily limit until midnight at the school', function () {
    config(['services.openwa.daily_limit' => 2]);
    waGuardNoPause();
    $instance = waGuardSchool();

    // 10:00 UTC is 17:00 in Jakarta: seven hours to midnight.
    expect(waGuardSend($instance)->sent)->toBeTrue()
        ->and(waGuardSend($instance)->sent)->toBeTrue();

    $held = waGuardSend($instance);

    expect($held->throttled)->toBeTrue()
        ->and($held->retryAfter)->toBe(7 * 3600)
        ->and($held->error)->toContain('harian')
        ->and(waGuardSends())->toBe(2)
        ->and(waGuardPause($instance)['until'])->not->toBeNull();

    $this->travel(7)->hours();

    expect(waGuardSend($instance)->sent)->toBeTrue();
});

it('does not count a message the gateway refused towards the daily limit', function () {
    config(['services.openwa.daily_limit' => 1]);
    waGuardNoPause();
    $instance = waGuardSchool();

    waGuardGatewayDown(422);
    expect(waGuardSend($instance)->sent)->toBeFalse();

    waGuardGatewayAnswers();

    expect(waGuardSend($instance)->sent)->toBeTrue()
        ->and(waGuardSend($instance)->throttled)->toBeTrue();
});

it('opens the breaker after consecutive retryable failures and closes it after the pause', function () {
    waGuardNoPause();
    $instance = waGuardSchool();
    waGuardGatewayDown();

    foreach (range(1, 3) as $ignored) {
        $failed = waGuardSend($instance);

        expect($failed->throttled)->toBeFalse()
            ->and($failed->retryable)->toBeTrue();
    }

    $held = waGuardSend($instance);
    $pause = waGuardPause($instance);

    expect($held->throttled)->toBeTrue()
        ->and($held->retryAfter)->toBe(300)
        ->and(waGuardSends())->toBe(3)
        ->and($pause['until'])->toBe(now()->addSeconds(300)->toIso8601String())
        ->and($pause['reason'])->not->toBeNull();

    $this->travel(301)->seconds();
    waGuardGatewayAnswers();

    expect(waGuardSend($instance)->sent)->toBeTrue()
        ->and(waGuardPause($instance))->toBe(['until' => null, 'reason' => null]);
});

it('does not open the breaker for refusals the gateway will keep giving', function () {
    waGuardNoPause();
    $instance = waGuardSchool();
    waGuardGatewayDown(422);

    foreach (range(1, 5) as $ignored) {
        expect(waGuardSend($instance)->throttled)->toBeFalse();
    }

    expect(waGuardSends())->toBe(5)
        ->and(waGuardPause($instance)['until'])->toBeNull();
});

it('starts counting failures again after a success', function () {
    waGuardNoPause();
    $instance = waGuardSchool();

    waGuardGatewayDown();
    waGuardSend($instance);
    waGuardSend($instance);

    waGuardGatewayAnswers();
    expect(waGuardSend($instance)->sent)->toBeTrue();

    waGuardGatewayDown();
    waGuardSend($instance);
    waGuardSend($instance);

    expect(waGuardSend($instance)->throttled)->toBeFalse();
});

it('counts an unreachable gateway as a failure too', function () {
    config(['services.openwa.breaker_threshold' => 2]);
    waGuardNoPause();
    $instance = waGuardSchool();
    waGuardGatewayDown(null);

    waGuardSend($instance);
    waGuardSend($instance);

    expect(waGuardSend($instance)->throttled)->toBeTrue();
});

it('keeps one school paused or over its limit from touching another', function () {
    waGuardNoPause();
    $a = waGuardSchool();
    $b = WhatsappInstanceFactory::new()->connected()->forTenant(TenantFactory::new()->create()->id)->create(['api_key' => WA_GUARD_KEY]);

    waGuardGatewayDown();

    foreach (range(1, 3) as $ignored) {
        waGuardSend($a);
    }

    expect(waGuardSend($a)->throttled)->toBeTrue()
        ->and(waGuardPause($a)['until'])->not->toBeNull()
        ->and(waGuardPause($b)['until'])->toBeNull();

    waGuardGatewayAnswers();

    expect(waGuardSend($b)->sent)->toBeTrue()
        ->and(waGuardSend($a)->throttled)->toBeTrue();
});

it('keeps pacing and the daily count apart per school', function () {
    config(['services.openwa.daily_limit' => 1]);
    $a = waGuardSchool();
    $b = WhatsappInstanceFactory::new()->connected()->forTenant(TenantFactory::new()->create()->id)->create(['api_key' => WA_GUARD_KEY]);

    expect(waGuardSend($a)->sent)->toBeTrue()
        ->and(waGuardSend($a)->throttled)->toBeTrue()
        ->and(waGuardSend($b)->sent)->toBeTrue();
});

it('keeps the guard state under the school partition of the cache', function () {
    $instance = waGuardSchool();

    waGuardSend($instance);

    $cache = app(TenantCache::class);
    $context = app(TenantContext::class);

    expect($context->run($instance->tenant_id, fn () => $cache->key('whatsapp:next-slot')))->toStartWith("tenant:{$instance->tenant_id}:")
        ->and($context->run($instance->tenant_id, fn () => (int) $cache->get('whatsapp:next-slot', 0)))->toBeGreaterThan(0)
        ->and($context->runWithoutTenant(fn () => (int) $cache->get('whatsapp:next-slot', 0)))->toBe(0);
});

it('never puts the session key in the state or in what a held message says', function () {
    $instance = waGuardSchool();
    waGuardSend($instance);

    $held = waGuardSend($instance);
    $state = app(TenantContext::class)->run($instance->tenant_id, fn () => app(WhatsappChannel::class)->state());
    $json = json_encode([$held, $state]);

    expect($json)->not->toContain(WA_GUARD_KEY)
        ->and($json)->not->toContain((string) $instance->session_id);
});
