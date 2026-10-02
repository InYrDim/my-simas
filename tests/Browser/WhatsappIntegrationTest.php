<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Core\App\Domain\Enums\WhatsappMessageStatus;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;

require_once __DIR__.'/Support/school.php';
require_once __DIR__.'/Support/onboarding.php';

/*
 * Integrasi WhatsApp (Fase 9) in a real browser, across both hosts: the
 * school asks, the provider approves, the school scans the QR and sends a
 * test message. The gateway is faked, so no request leaves the test and
 * no message is sent; every refusal, permission and tenant isolation rule
 * is covered by the Platform and Core feature tests.
 */

it('takes a school from asking for WhatsApp to a sent test message', function () {
    Http::preventStrayRequests();

    config([
        'services.openwa.base_url' => 'https://wa.test',
        'services.openwa.admin_api_key' => 'owa_k1_admin-key-for-tests',
        'services.openwa.credentials_key' => str_repeat('ab', 32),
    ]);

    // The gateway's session: `created` until started, then showing a QR,
    // then linked once the test says the phone scanned it.
    $session = 'created';
    $qr = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    Http::fake(function (Request $request) use (&$session, $qr) {
        $url = $request->url();

        return match (true) {
            str_ends_with($url, '/api/sessions') => Http::response(['id' => 'session-uji', 'status' => 'created'], 201),
            str_ends_with($url, '/api/auth/api-keys') => Http::response(['id' => 'key-uji', 'apiKey' => 'owa_k1_school-key-for-tests'], 201),
            str_ends_with($url, '/start') => tap(Http::response(['status' => 'initializing']), function () use (&$session): void {
                $session = 'qr_ready';
            }),
            str_ends_with($url, '/qr') => Http::response(['qrCode' => $qr, 'status' => 'qr_ready']),
            str_ends_with($url, '/messages/send-text') => Http::response(['messageId' => 'pesan-uji-1'], 201),
            default => Http::response($session === 'ready'
                ? ['status' => 'ready', 'phone' => '628111000111', 'pushName' => 'TU Sekolah Uji']
                : ['status' => $session]),
        };
    });

    // 1. The school admin asks for WhatsApp.
    [$page, $tenant] = schoolMemberSignsIn();

    $page->navigate('/integrasi/whatsapp')
        ->assertSee('Belum diajukan')
        ->press('Ajukan WhatsApp')
        ->assertSee('Menunggu persetujuan');

    $instance = WhatsappInstance::query()->where('tenant_id', $tenant->id)->sole();

    expect($instance->status)->toBe(WhatsappInstanceStatus::Pending);

    // 2. The provider approves it in the console.
    $console = providerSignsIn();

    $console->navigate('/whatsapp')
        ->assertSee($tenant->name)
        ->press('Setujui')
        ->assertSee('disetujui')
        ->assertNoJavaScriptErrors();

    expect($instance->refresh()->status)->toBe(WhatsappInstanceStatus::Active)
        ->and($instance->session_id)->toBe('session-uji');

    // 3. The school admin links the number: QR first, then linked. The
    // console requests ran in this same process and left `provider` as the
    // default guard (auth:provider calls shouldUse), so the Gate would ask
    // the provider's user for a school permission. A real server starts
    // every request on the configured default.
    onCentralHost();
    app('auth')->shouldUse('web');
    refreshSignedInUsers();

    $page->navigate('/integrasi/whatsapp')
        ->assertSee('Disetujui')
        ->press('Hubungkan')
        ->assertSee('Pindai kode QR')
        ->assertVisible('img[alt="Kode QR untuk menautkan WhatsApp"]');

    $session = 'ready';

    // The page asks again by itself every few seconds.
    $page->assertSee('628111000111')
        ->assertSee('TU Sekolah Uji')
        ->assertSee('Terhubung');

    expect($instance->refresh()->phone)->toBe('628111000111');

    // 4. A test message goes out and shows in the history.
    $page->press('Kirim pesan uji')
        ->fill('phone', '0812-3456-7890')
        ->click('internal:role=button[name="Kirim"s]')
        ->assertSee('Terkirim ke WhatsApp')
        ->assertSee('62812••••7890')
        ->assertDontSee('owa_k1_')
        ->assertNoJavaScriptErrors();

    $message = inTenant($tenant, fn () => WhatsappMessage::query()->sole());

    expect($message->status)->toBe(WhatsappMessageStatus::Sent)
        ->and($message->gateway_message_id)->toBe('pesan-uji-1');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/sessions/session-uji/messages/send-text')
        && $request->header('X-API-Key') === ['owa_k1_school-key-for-tests']
        && $request['chatId'] === '6281234567890@c.us');
});
