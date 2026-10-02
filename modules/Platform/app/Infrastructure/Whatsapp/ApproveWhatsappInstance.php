<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Illuminate\Support\Str;
use Modules\Platform\App\Domain\Models\WhatsappInstance;
use Modules\Platform\App\Domain\Models\WhatsappInstanceStatus;

/**
 * Approve a school's WhatsApp request: register its session on the
 * gateway, mint a key that works for that session only, and keep both.
 *
 * Nothing is half-approved: when the gateway refuses, the request stays
 * pending with the reason in `last_error`, and approving again picks up
 * the session a previous attempt may have left behind.
 */
final class ApproveWhatsappInstance
{
    public function __construct(
        private readonly OpenWaClient $gateway,
        private readonly CredentialVault $vault,
    ) {}

    /**
     * @param  int|null  $decidedBy  provider user id; null when approved automatically
     *
     * @throws InstanceStatusException
     * @throws OpenWaException
     */
    public function handle(WhatsappInstance $instance, ?int $decidedBy): WhatsappInstance
    {
        if ($instance->status !== WhatsappInstanceStatus::Pending) {
            throw InstanceStatusException::notPending();
        }

        try {
            // Checked before anything exists on the gateway: a key that
            // cannot be stored encrypted must not be minted at all.
            if (! $this->vault->ready()) {
                throw new OpenWaException('OPENWA_CREDENTIALS_KEY belum diatur atau bukan 64 karakter hex.');
            }

            $name = self::sessionName($instance->tenant_id);
            $session = $this->session($name);
            $key = $this->gateway->createSessionKey("SIMAS {$name}", $session['id']);
        } catch (OpenWaException $exception) {
            $instance->forceFill(['last_error' => $exception->getMessage()])->save();

            throw $exception;
        }

        $instance->forceFill([
            'status' => WhatsappInstanceStatus::Active,
            'decided_by' => $decidedBy,
            'decided_at' => now(),
            'note' => null,
            'session_id' => $session['id'],
            'session_name' => $name,
            'api_key' => $key['apiKey'],
            'api_key_id' => $key['id'],
            'connection_status' => $session['status'],
            'last_error' => null,
        ])->save();

        return $instance;
    }

    /**
     * One session per school, named after its id: always valid for the
     * gateway (letters, digits, hyphens; 32 characters) and never shared.
     */
    public static function sessionName(string $tenantId): string
    {
        return 'simas-'.Str::lower($tenantId);
    }

    /**
     * The school's session: a new one, or the one already registered under
     * its name (the gateway answers 409 for a taken name).
     *
     * @return array{id: string, status: string}
     */
    private function session(string $name): array
    {
        try {
            $session = $this->gateway->createSession($name);
        } catch (OpenWaException $exception) {
            if ($exception->status !== 409) {
                throw $exception;
            }

            $session = $this->gateway->findSessionByName($name);
        }

        if (! is_string($session['id'] ?? null) || $session['id'] === '') {
            throw new OpenWaException('OpenWA tidak mengembalikan sesi untuk sekolah ini.');
        }

        return [
            'id' => $session['id'],
            'status' => is_string($session['status'] ?? null) ? $session['status'] : 'created',
        ];
    }
}
