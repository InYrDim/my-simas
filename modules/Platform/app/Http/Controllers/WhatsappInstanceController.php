<?php

namespace Modules\Platform\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\App\Domain\Models\ProviderSetting;
use Modules\Platform\App\Domain\Models\ProviderUser;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Http\Support\ConsoleResources;
use Modules\Platform\App\Infrastructure\Whatsapp\ApproveWhatsappInstance;
use Modules\Platform\App\Infrastructure\Whatsapp\CredentialVault;
use Modules\Platform\App\Infrastructure\Whatsapp\DisableWhatsappInstance;
use Modules\Platform\App\Infrastructure\Whatsapp\EnableWhatsappInstance;
use Modules\Platform\App\Infrastructure\Whatsapp\InstanceStatusException;
use Modules\Platform\App\Infrastructure\Whatsapp\OpenWaException;
use Modules\Platform\App\Infrastructure\Whatsapp\RejectWhatsappInstance;

/**
 * Provider console: the schools' WhatsApp requests. Approving registers
 * the school's session on the OpenWA gateway; the session key it mints is
 * stored encrypted and shown nowhere, this console included.
 */
final class WhatsappInstanceController
{
    public function index(CredentialVault $vault): Response
    {
        $instances = WhatsappInstance::query()
            ->with('tenant')
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderBy('requested_at')
            ->orderBy('id')
            ->get();

        return Inertia::render('Platform/Whatsapp/Index', [
            'instances' => $instances
                ->map(fn (WhatsappInstance $instance): array => ConsoleResources::whatsappInstance($instance))
                ->all(),
            'autoApprove' => ProviderSetting::read(ProviderSetting::WHATSAPP_AUTO_APPROVE, false) === true,
            'gatewayReady' => (string) config('services.openwa.base_url') !== ''
                && (string) config('services.openwa.admin_api_key') !== ''
                && $vault->ready(),
        ]);
    }

    public function approve(Request $request, WhatsappInstance $instance, ApproveWhatsappInstance $approve): RedirectResponse
    {
        /** @var ProviderUser $provider */
        $provider = $request->user('provider');

        try {
            $approve->handle($instance, (int) $provider->id);
        } catch (InstanceStatusException|OpenWaException $exception) {
            return back()->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        return back()->with('status', "WhatsApp {$this->schoolName($instance)} disetujui.");
    }

    public function reject(Request $request, WhatsappInstance $instance, RejectWhatsappInstance $reject): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var ProviderUser $provider */
        $provider = $request->user('provider');

        try {
            $reject->handle($instance, $validated['note'] ?? null, (int) $provider->id);
        } catch (InstanceStatusException $exception) {
            return back()->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        return back()->with('status', "Pengajuan WhatsApp {$this->schoolName($instance)} ditolak.");
    }

    /**
     * Switch a school's WhatsApp off and stop its session. The linked
     * device is kept for when it is enabled again.
     */
    public function disable(Request $request, WhatsappInstance $instance, DisableWhatsappInstance $disable): RedirectResponse
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var ProviderUser $provider */
        $provider = $request->user('provider');

        try {
            $disable->handle($instance, $validated['note'] ?? null, (int) $provider->id);
        } catch (InstanceStatusException $exception) {
            return back()->withErrors(['whatsapp' => $exception->getMessage()]);
        } catch (OpenWaException $exception) {
            return back()->withErrors([
                'whatsapp' => "WhatsApp {$this->schoolName($instance)} dinonaktifkan di SIMAS, tetapi sesinya di gateway belum berhenti: {$exception->getMessage()}",
            ]);
        }

        return back()->with('status', "WhatsApp {$this->schoolName($instance)} dinonaktifkan.");
    }

    public function enable(Request $request, WhatsappInstance $instance, EnableWhatsappInstance $enable): RedirectResponse
    {
        /** @var ProviderUser $provider */
        $provider = $request->user('provider');

        try {
            $enable->handle($instance, (int) $provider->id);
        } catch (InstanceStatusException $exception) {
            return back()->withErrors(['whatsapp' => $exception->getMessage()]);
        }

        return back()->with('status', "WhatsApp {$this->schoolName($instance)} diaktifkan lagi.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'auto_approve' => ['required', 'boolean'],
        ]);

        $autoApprove = (bool) $validated['auto_approve'];

        ProviderSetting::write(ProviderSetting::WHATSAPP_AUTO_APPROVE, $autoApprove);

        return back()->with('status', $autoApprove
            ? 'Pengajuan WhatsApp baru akan disetujui otomatis.'
            : 'Pengajuan WhatsApp baru menunggu persetujuan Anda.');
    }

    private function schoolName(WhatsappInstance $instance): string
    {
        return $instance->tenant->name ?? 'sekolah';
    }
}
