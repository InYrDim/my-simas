<?php

namespace Modules\Core\Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Core\App\Contracts\NoticeRegistry;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;
use Modules\Platform\App\Contracts\DTOs\WhatsappSendResult;
use Modules\Platform\App\Contracts\DTOs\WhatsappState;
use Modules\Platform\App\Contracts\Exceptions\WhatsappRiskNotAcknowledgedException;
use Modules\Platform\App\Contracts\WhatsappChannel;
use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;
use Modules\Platform\Database\Factories\TenantFactory;
use Modules\Platform\Database\Factories\WhatsappInstanceFactory;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/Support/helpers.php';
require_once __DIR__.'/Support/notices.php';

/*
 * Integrasi › WhatsApp on the school side (fase 9, stage 1): who may open
 * it, asking the provider for WhatsApp, and what the page is told. The
 * gateway is faked: no request leaves the test.
 */

const WHATSAPP_PAGE_KEY = 'owa_k1_school-session-key-for-tests';

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

it('shows an admin that WhatsApp has not been asked for yet', function () {
    $tenant = schoolAs('wa-none');

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Core/Integration/Whatsapp/Index')
        ->where('state.stage', 'none')
        ->where('state.connection', null)
        ->where('state.requestedAt', null)
    );
});

it('keeps the page, the request and the menu away from other roles', function (string $role) {
    $tenant = schoolAs("wa-{$role}", $role);

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertForbidden();
    post(school($tenant->slug, '/integrasi/whatsapp/ajukan'))->assertForbidden();
    post(school($tenant->slug, '/integrasi/whatsapp/hubungkan'))->assertForbidden();
    post(school($tenant->slug, '/integrasi/whatsapp/putuskan'))->assertForbidden();

    get(school($tenant->slug, '/beranda'))->assertInertia(fn (Assert $page) => $page
        ->where('tenantNav', fn ($nav) => ! collect($nav)->pluck('label')->contains('Integrasi'))
    );

    expect(WhatsappInstance::query()->count())->toBe(0);
})->with(['guru', 'staf-tu', 'siswa']);

it('sends guests to the login', function () {
    $tenant = TenantFactory::new()->create(['slug' => 'wa-guest']);

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertRedirect();
    post(school($tenant->slug, '/integrasi/whatsapp/ajukan'))->assertRedirect();

    expect(WhatsappInstance::query()->count())->toBe(0);
});

it('lets an admin ask for WhatsApp and shows the request waiting', function () {
    $tenant = schoolAs('wa-ask');

    post(school($tenant->slug, '/integrasi/whatsapp/ajukan'))
        ->assertRedirect()
        ->assertSessionHas('status', 'Pengajuan WhatsApp dikirim. Menunggu persetujuan.');

    $instance = WhatsappInstance::query()->where('tenant_id', $tenant->id)->sole();

    expect($instance->status)->toBe(WhatsappInstanceStatus::Pending)
        ->and($instance->requested_by)->toBe(auth()->id());

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.stage', 'pending')
        ->whereNot('state.requestedAt', null)
    );

    Http::assertNothingSent();
});

it('does not duplicate the request when asked again', function () {
    $tenant = schoolAs('wa-twice');

    post(school($tenant->slug, '/integrasi/whatsapp/ajukan'))->assertRedirect();
    post(school($tenant->slug, '/integrasi/whatsapp/ajukan'))->assertRedirect();

    expect(WhatsappInstance::query()->where('tenant_id', $tenant->id)->count())->toBe(1);
});

it('tells the admin when the request was approved on the spot', function () {
    Http::fake([
        'wa.test/api/sessions' => Http::response(['id' => 'session-1', 'status' => 'created'], 201),
        'wa.test/api/auth/api-keys' => Http::response(['id' => 'key-1', 'apiKey' => WHATSAPP_PAGE_KEY], 201),
        'wa.test/api/sessions/session-1' => Http::response(['id' => 'session-1', 'status' => 'created']),
    ]);
    ProviderSetting::write(ProviderSetting::WHATSAPP_AUTO_APPROVE, true);

    $tenant = schoolAs('wa-auto');

    post(school($tenant->slug, '/integrasi/whatsapp/ajukan'))
        ->assertRedirect()
        ->assertSessionHas('status', 'WhatsApp sekolah disetujui.');

    $response = get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.stage', 'active')
        ->where('state.connection', 'created')
    );

    expect($response->getContent())->not->toContain('owa_k1_');
});

it('shows the providers note on a rejected request', function () {
    $tenant = schoolAs('wa-rejected');
    WhatsappInstanceFactory::new()->forTenant($tenant->id)->rejected('Lengkapi profil sekolah dulu.')->create();

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.stage', 'rejected')
        ->where('state.note', 'Lengkapi profil sekolah dulu.')
    );
});

it('never hands the session key, its id or the session id to the page', function () {
    Http::fake(['wa.test/api/sessions/*' => Http::response(['status' => 'ready', 'phone' => '628111000111', 'pushName' => 'TU Sekolah'])]);
    $tenant = schoolAs('wa-secret');
    $instance = WhatsappInstanceFactory::new()->forTenant($tenant->id)->connected('628111000111')->create([
        'api_key' => WHATSAPP_PAGE_KEY,
    ]);

    $response = get(school($tenant->slug, '/integrasi/whatsapp'))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->where('state.stage', 'active')
        ->where('state.connection', 'ready')
        ->where('state.phone', '628111000111')
        ->where('state', fn ($state) => collect($state)->keys()->sort()->values()->all() === [
            'connection', 'lastError', 'note', 'pauseReason', 'pausedUntil', 'phone', 'pushName', 'qrCode', 'requestedAt', 'riskAcknowledged', 'stage',
        ])
    );

    expect($response->getContent())
        ->not->toContain('owa_k1_')
        ->not->toContain($instance->api_key_id)
        ->not->toContain($instance->session_id);
});

it('shows only the own schools request', function () {
    $other = TenantFactory::new()->create(['slug' => 'wa-other']);
    WhatsappInstanceFactory::new()->forTenant($other->id)->connected('628999000999')->create();

    $tenant = schoolAs('wa-own');

    $response = get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.stage', 'none')
        ->where('state.phone', null)
    );

    expect($response->getContent())->not->toContain('628999000999');

    post(school($tenant->slug, '/integrasi/whatsapp/ajukan'))->assertRedirect();

    expect(WhatsappInstance::query()->where('tenant_id', $other->id)->sole()->status)->toBe(WhatsappInstanceStatus::Active)
        ->and(WhatsappInstance::query()->where('tenant_id', $tenant->id)->sole()->status)->toBe(WhatsappInstanceStatus::Pending);
});

/**
 * The gateway reports the session in these states, one per read, and
 * accepts every other call. (One closure: with a URL map, every stub is
 * invoked for every request, which would use up the states.)
 *
 * @param  list<array<string, mixed>>  $reads
 */
function whatsappGatewaySays(array $reads): void
{
    Http::fake(function (Request $request) use (&$reads) {
        $url = $request->url();

        if (str_ends_with($url, '/qr')) {
            return Http::response(['qrCode' => 'data:image/png;base64,QRQRQR', 'status' => 'qr_ready']);
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

it('links the number: connect, the QR, then the linked number', function () {
    whatsappGatewaySays([
        ['status' => 'created'],
        ['status' => 'initializing'],
        ['status' => 'qr_ready'],
        ['status' => 'ready', 'phone' => '628111000111', 'pushName' => 'TU Sekolah'],
    ]);

    $tenant = schoolAs('wa-link');
    $instance = WhatsappInstanceFactory::new()->forTenant($tenant->id)->active()->create(['api_key' => WHATSAPP_PAGE_KEY, 'risk_acknowledged_by' => 1, 'risk_acknowledged_at' => now()]);

    post(school($tenant->slug, '/integrasi/whatsapp/hubungkan'))->assertRedirect()->assertSessionHasNoErrors();

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), "/sessions/{$instance->session_id}/start"));

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.connection', 'initializing')
        ->where('state.qrCode', null)
    );

    $scanning = get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.connection', 'qr_ready')
        ->where('state.qrCode', 'data:image/png;base64,QRQRQR')
    );

    $linked = get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.connection', 'ready')
        ->where('state.phone', '628111000111')
        ->where('state.pushName', 'TU Sekolah')
        ->where('state.qrCode', null)
    );

    expect($scanning->getContent().$linked->getContent())
        ->not->toContain('owa_k1_')
        ->not->toContain($instance->session_id);
});

it('re-reads only the state while a link is under way', function () {
    whatsappGatewaySays([['status' => 'qr_ready'], ['status' => 'authenticating']]);

    $tenant = schoolAs('wa-poll');
    WhatsappInstanceFactory::new()->forTenant($tenant->id)->active()->create(['connection_status' => 'initializing']);

    $version = get(school($tenant->slug, '/integrasi/whatsapp'))
        ->assertInertia(fn (Assert $page) => $page->where('state.connection', 'qr_ready')->has('history'))
        ->inertiaPage()['version'];

    get(school($tenant->slug, '/integrasi/whatsapp'), [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'Core/Integration/Whatsapp/Index',
        'X-Inertia-Partial-Data' => 'state',
    ])
        ->assertOk()
        ->assertJsonPath('props.state.connection', 'authenticating')
        ->assertJsonPath('props.state.qrCode', null)
        ->assertJsonMissingPath('props.history');
});

it('unlinks the number', function () {
    whatsappGatewaySays([['status' => 'disconnected']]);

    $tenant = schoolAs('wa-unlink');
    $instance = WhatsappInstanceFactory::new()->forTenant($tenant->id)->connected('628111000111')->create();

    post(school($tenant->slug, '/integrasi/whatsapp/putuskan'))
        ->assertRedirect()
        ->assertSessionHas('status', 'WhatsApp sekolah diputuskan.');

    expect($instance->refresh()->phone)->toBeNull()
        ->and($instance->connection_status)->toBe('disconnected');

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.stage', 'active')
        ->where('state.connection', 'disconnected')
        ->where('state.phone', null)
    );
});

it('refuses to link before the provider approved, or after it switched the school off', function (string $factoryState) {
    $tenant = schoolAs("wa-refuse-{$factoryState}");
    WhatsappInstanceFactory::new()->forTenant($tenant->id)->{$factoryState}()->create();

    post(school($tenant->slug, '/integrasi/whatsapp/hubungkan'))->assertSessionHasErrors('status');
    post(school($tenant->slug, '/integrasi/whatsapp/putuskan'))->assertSessionHasErrors('status');

    Http::assertNothingSent();
})->with(['pending', 'rejected', 'disabled']);

it('tells the admin when the gateway cannot be reached, without gateway details', function () {
    Http::fake(['wa.test/*' => Http::response(null, 502)]);

    $tenant = schoolAs('wa-down');
    $instance = WhatsappInstanceFactory::new()->forTenant($tenant->id)->active()->create(['risk_acknowledged_by' => 1, 'risk_acknowledged_at' => now()]);

    post(school($tenant->slug, '/integrasi/whatsapp/hubungkan'))
        ->assertRedirect()
        ->assertSessionHasErrors(['status' => 'Gateway WhatsApp menjawab galat 502. Coba lagi beberapa saat lagi.']);

    $response = get(school($tenant->slug, '/integrasi/whatsapp'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('state.stage', 'active')
        ->where('state.lastError', 'Gateway WhatsApp menjawab galat 502. Coba lagi beberapa saat lagi.')
    );

    expect($response->getContent())->not->toContain($instance->session_id);
});

it('shows why the provider switched WhatsApp off', function () {
    $tenant = schoolAs('wa-disabled');
    WhatsappInstanceFactory::new()->forTenant($tenant->id)->disabled('Tagihan menunggak.')->create();

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.stage', 'disabled')
        ->where('state.note', 'Tagihan menunggak.')
        ->where('state.connection', null)
    );

    Http::assertNothingSent();
});

/**
 * A channel that records who accepted the risk and can refuse to link
 * until then, so these tests do not depend on Platform's gateway code.
 */
function riskChannel(?WhatsappState $state = null): object
{
    $channel = new class($state ?? new WhatsappState(stage: WhatsappState::STAGE_ACTIVE)) implements WhatsappChannel
    {
        /** @var list<int> */
        public array $acknowledgedBy = [];

        public bool $refuseConnect = false;

        public function __construct(public WhatsappState $state) {}

        public function state(bool $refresh = false): WhatsappState
        {
            return $this->state;
        }

        public function request(int $requestedBy): WhatsappState
        {
            return $this->state;
        }

        public function connect(): WhatsappState
        {
            if ($this->refuseConnect) {
                throw new WhatsappRiskNotAcknowledgedException;
            }

            return $this->state;
        }

        public function acknowledgeRisk(int $userId): WhatsappState
        {
            $this->acknowledgedBy[] = $userId;

            return $this->state;
        }

        public function disconnect(): WhatsappState
        {
            return $this->state;
        }

        public function sendText(string $phone, string $text): WhatsappSendResult
        {
            return WhatsappSendResult::unavailable();
        }
    };

    app()->instance(WhatsappChannel::class, $channel);

    return $channel;
}

function registerBulkNotice(string $key = 'uji.bulk'): void
{
    app(NoticeRegistry::class)->register('core', new NoticeKind(
        key: $key,
        title: 'Siswa masuk sekolah',
        description: 'Dikirim untuk setiap scan gerbang.',
        recipient: 'Wali murid',
        template: '{Halo|Hai} {nama_wali}, {nama_siswa} sudah masuk.',
        highVolume: true,
    ));
}

it('hands the page the pause and the risk acknowledgement, and marks high volume kinds', function () {
    riskChannel(new WhatsappState(
        stage: WhatsappState::STAGE_ACTIVE,
        connection: 'ready',
        pausedUntil: '2026-10-07T10:30:00+00:00',
        pauseReason: 'Pengiriman dijeda setelah beberapa kegagalan.',
        riskAcknowledged: true,
    ));
    $tenant = schoolAs('wa-state');
    registerBulkNotice();
    registerAbsenceNotice();

    get(school($tenant->slug, '/integrasi/whatsapp'))->assertInertia(fn (Assert $page) => $page
        ->where('state.pausedUntil', '2026-10-07T10:30:00+00:00')
        ->where('state.pauseReason', 'Pengiriman dijeda setelah beberapa kegagalan.')
        ->where('state.riskAcknowledged', true)
        ->where('kinds', fn ($kinds) => collect($kinds)->firstWhere('key', 'uji.bulk')['highVolume'] === true
            && collect($kinds)->firstWhere('key', 'uji.absent')['highVolume'] === false)
    );
});

it('records the admin who accepted the risk through the channel', function () {
    $channel = riskChannel();
    $tenant = schoolAs('wa-ack');

    post(school($tenant->slug, '/integrasi/whatsapp/setujui-risiko'))
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    expect($channel->acknowledgedBy)->toBe([auth()->id()]);
});

it('keeps the risk acknowledgement away from other roles and guests', function (string $role) {
    $channel = riskChannel();
    $tenant = schoolAs("wa-ack-{$role}", $role);

    post(school($tenant->slug, '/integrasi/whatsapp/setujui-risiko'))->assertForbidden();

    signOutOfSchool();

    post(school($tenant->slug, '/integrasi/whatsapp/setujui-risiko'))->assertRedirect();

    expect($channel->acknowledgedBy)->toBe([]);
})->with(['guru', 'staf-tu', 'siswa']);

it('tells the admin to accept the risk first when linking is refused for it', function () {
    $channel = riskChannel();
    $channel->refuseConnect = true;
    $tenant = schoolAs('wa-ack-first');

    post(school($tenant->slug, '/integrasi/whatsapp/hubungkan'))
        ->assertRedirect()
        ->assertSessionHasErrors(['status' => 'Setujui peringatan risiko WhatsApp lebih dulu sebelum menghubungkan nomor.']);
});

it('switches a high volume notice on only with the volume confirmed', function () {
    $tenant = schoolAs('wa-volume');
    registerBulkNotice();
    $url = school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.bulk');

    $this->put($url, ['enabled' => true])->assertSessionHasErrors('confirm_volume');
    $this->put($url, ['enabled' => true, 'confirm_volume' => false])->assertSessionHasErrors('confirm_volume');

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->count()))->toBe(0);

    $this->put($url, ['enabled' => true, 'confirm_volume' => true])->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->where('kind', 'uji.bulk')->sole()->enabled))->toBeTrue();

    // Already on: new wording does not ask again; switching off never does.
    $this->put($url, ['enabled' => true, 'template' => 'Halo {nama_siswa}'])->assertSessionHasNoErrors();
    $this->put($url, ['enabled' => false])->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->where('kind', 'uji.bulk')->sole()->enabled))->toBeFalse();
});

it('does not ask for the volume of an ordinary notice', function () {
    $tenant = schoolAs('wa-ordinary');
    registerAbsenceNotice();

    $this->put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), ['enabled' => true])
        ->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->sole()->enabled))->toBeTrue();
});

it('refuses wording with a malformed variation group and stores nothing', function (string $template) {
    $tenant = schoolAs('wa-groups');
    registerAbsenceNotice();

    $this->put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), ['enabled' => true, 'template' => $template])
        ->assertSessionHasErrors('template');

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->count()))->toBe(0);
})->with([
    'one empty choice' => ['Halo {a|} {nama_siswa}'],
    'nested' => ['{a|{b|c}} {nama_siswa}'],
]);

it('keeps wording with variation groups', function () {
    $tenant = schoolAs('wa-groups-ok');
    registerAbsenceNotice();

    $this->put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), ['enabled' => true, 'template' => '{Halo|Hai} {nama_siswa}'])
        ->assertSessionHasNoErrors();

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->sole()->template))->toBe('{Halo|Hai} {nama_siswa}');
});

it('keeps the reply line choice: default stays null, own wording is stored', function () {
    $tenant = schoolAs('wa-footer');
    registerAbsenceNotice();
    $url = school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent');

    $this->put($url, ['enabled' => true, 'reply_footer' => true, 'reply_footer_text' => null])->assertSessionHasNoErrors();

    $setting = inSchool($tenant, fn () => WhatsappNoticeSetting::query()->sole());
    expect($setting->reply_footer)->toBeTrue()->and($setting->reply_footer_text)->toBeNull();

    $this->put($url, ['enabled' => true, 'reply_footer' => true, 'reply_footer_text' => 'Balas ya, {nama_wali}.'])->assertSessionHasNoErrors();
    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->sole()->reply_footer_text))->toBe('Balas ya, {nama_wali}.');

    $this->put($url, ['enabled' => true])->assertSessionHasNoErrors();
    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->sole()->reply_footer))->toBeFalse();
});

it('refuses a malformed variation group in the reply line', function () {
    $tenant = schoolAs('wa-footer-bad');
    registerAbsenceNotice();

    $this->put(school($tenant->slug, '/integrasi/whatsapp/pemberitahuan/uji.absent'), ['enabled' => true, 'reply_footer' => true, 'reply_footer_text' => 'Balas {a|}'])
        ->assertSessionHasErrors('reply_footer_text');

    expect(inSchool($tenant, fn () => WhatsappNoticeSetting::query()->count()))->toBe(0);
});
