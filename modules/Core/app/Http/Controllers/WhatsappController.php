<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Modules\Core\App\Contracts\DTOs\NoticeKind;
use Modules\Core\App\Domain\Actions\SaveNoticeSetting;
use Modules\Core\App\Domain\Actions\SendTestMessage;
use Modules\Core\App\Domain\Models\WhatsappMessage;
use Modules\Core\App\Domain\Models\WhatsappNoticeSetting;
use Modules\Core\App\Http\Concerns\RendersMasterPage;
use Modules\Core\App\Infrastructure\Whatsapp\DefaultNoticeRegistry;
use Modules\Core\App\Infrastructure\Whatsapp\NoticeTemplate;
use Modules\Core\App\Infrastructure\Whatsapp\PhoneNumber;
use Modules\Platform\App\Contracts\DTOs\WhatsappState;
use Modules\Platform\App\Contracts\Exceptions\WhatsappUnavailableException;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\WhatsappChannel;

/**
 * Integrasi › WhatsApp: the school asks the provider for WhatsApp, then
 * links its number by scanning a QR. The gateway and its credentials are
 * Platform's (WhatsappChannel); this page only ever receives the state,
 * and re-reads it by polling while a link is under way.
 *
 * Messages go out through the queue and every one is logged; the history
 * on this page is that log. The notices listed are the kinds modules
 * registered (NoticeRegistry): the school switches each on or off and may
 * reword it.
 */
final class WhatsappController
{
    use RendersMasterPage;

    private const PER_PAGE = 15;

    /** Test messages one admin may send per minute. */
    private const TEST_LIMIT = 5;

    public function __construct(
        private readonly WhatsappChannel $whatsapp,
        private readonly TenantContext $context,
        private readonly DefaultNoticeRegistry $notices,
    ) {}

    public function index(): Response
    {
        $messages = WhatsappMessage::query()->latest('id')->paginate(self::PER_PAGE);

        return $this->renderMaster('Core/Integration/Whatsapp/Index', [
            'state' => fn (): array => $this->stateProps($this->whatsapp->state(refresh: true)),
            'kinds' => $this->kinds(),
            'history' => $messages->getCollection()->map(fn (WhatsappMessage $message): array => $this->historyRow($message))->all(),
            'pagination' => $this->paginationMeta($messages),
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $state = $this->whatsapp->request((int) $request->user()->getAuthIdentifier());

        return back()->with('status', $state->stage === WhatsappState::STAGE_ACTIVE
            ? 'WhatsApp sekolah disetujui.'
            : 'Pengajuan WhatsApp dikirim. Menunggu persetujuan.');
    }

    public function connect(): RedirectResponse
    {
        try {
            $state = $this->whatsapp->connect();
        } catch (WhatsappUnavailableException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return $state->lastError === null
            ? back()
            : back()->withErrors(['status' => $state->lastError]);
    }

    public function disconnect(): RedirectResponse
    {
        try {
            $state = $this->whatsapp->disconnect();
        } catch (WhatsappUnavailableException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return $state->lastError === null
            ? back()->with('status', 'WhatsApp sekolah diputuskan.')
            : back()->withErrors(['status' => $state->lastError]);
    }

    /**
     * Send a test message to one number. Rate limited per admin: every
     * one of these is a real WhatsApp message from the school's number.
     */
    public function test(Request $request, SendTestMessage $send): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
        ]);

        $throttleKey = sprintf(
            'whatsapp-test:%s:%s:%s',
            $this->context->currentOrFail()->id,
            $request->user()->getAuthIdentifier(),
            $request->ip(),
        );

        if (RateLimiter::tooManyAttempts($throttleKey, self::TEST_LIMIT)) {
            throw ValidationException::withMessages([
                'phone' => 'Terlalu banyak pesan uji. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        $send->handle($validated['phone']);

        RateLimiter::hit($throttleKey, 60);

        return back()->with('status', 'Pesan uji masuk antrean. Lihat hasilnya di riwayat pesan.');
    }

    /**
     * Switch one kind of notice on or off and keep the school's wording.
     */
    public function updateNotice(Request $request, string $kind, SaveNoticeSetting $save): RedirectResponse
    {
        $notice = $this->notices->find($kind) ?? abort(404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'template' => ['nullable', 'string', 'max:1000'],
        ]);

        $setting = $save->handle($notice, (bool) $validated['enabled'], $validated['template'] ?? null);

        return back()->with('status', $setting->enabled
            ? "Pemberitahuan \"{$notice->title}\" aktif."
            : "Pemberitahuan \"{$notice->title}\" tidak aktif.");
    }

    /**
     * The notices of this school: the kinds its modules registered, with
     * the school's switch and wording, then the announced ones.
     *
     * @return list<array<string, mixed>>
     */
    private function kinds(): array
    {
        $settings = WhatsappNoticeSetting::query()->get()->keyBy('kind');

        $available = array_map(function (NoticeKind $kind) use ($settings): array {
            $setting = $settings->get($kind->key);

            return [
                'key' => $kind->key,
                'title' => $kind->title,
                'description' => $kind->description,
                'recipient' => $kind->recipient,
                'available' => true,
                'enabled' => $setting !== null && $setting->enabled,
                'template' => $setting === null ? $kind->template : ($setting->template ?? $kind->template),
                'defaultTemplate' => $kind->template,
                'sample' => [...$kind->variables, ...NoticeTemplate::COMMON],
            ];
        }, $this->notices->available());

        $upcoming = array_map(fn (array $kind): array => [
            ...$kind,
            'available' => false,
            'enabled' => false,
            'template' => '',
            'defaultTemplate' => '',
            'sample' => [],
        ], $this->notices->upcoming());

        return [...$available, ...$upcoming];
    }

    /**
     * One line of the message log. The number is masked: the log is a
     * record of what was sent, not an address book.
     *
     * @return array{id: int, at: string, recipient: string, to: string, kind: string, status: string, error: string|null}
     */
    private function historyRow(WhatsappMessage $message): array
    {
        return [
            'id' => $message->id,
            'at' => $message->created_at->timezone($this->context->timezone())->isoFormat('D MMM YYYY, HH.mm'),
            'recipient' => $message->recipient_name,
            'to' => PhoneNumber::mask($message->phone),
            'kind' => $message->kind === WhatsappMessage::KIND_TEST
                ? 'Pesan uji'
                : ($this->notices->find($message->kind)->title ?? $message->kind),
            'status' => $message->status->value,
            'error' => $message->error,
        ];
    }

    /**
     * @return array{stage: string, connection: string|null, phone: string|null, pushName: string|null, note: string|null, qrCode: string|null, lastError: string|null, requestedAt: string|null}
     */
    private function stateProps(WhatsappState $state): array
    {
        return [
            'stage' => $state->stage,
            'connection' => $state->connection,
            'phone' => $state->phone,
            'pushName' => $state->pushName,
            'note' => $state->note,
            'qrCode' => $state->qrCode,
            'lastError' => $state->lastError,
            'requestedAt' => $state->requestedAt,
        ];
    }
}
