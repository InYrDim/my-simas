<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Illuminate\Support\Str;
use Modules\Platform\App\Domain\Models\WhatsappInstance;

/**
 * Ask the gateway where a school's session stands and keep the answer on
 * the instance: status, linked number, and the gateway's own error text.
 *
 * `last_error` is written here in words a school may read: never the
 * call, the session id or a key.
 */
final class SyncWhatsappSession
{
    /** The session's engine is up: starting it again would be refused. */
    public const RUNNING = ['initializing', 'qr_ready', 'authenticating', 'ready'];

    public function __construct(
        private readonly OpenWaClient $gateway,
    ) {}

    /**
     * @return string|null the QR to scan (PNG data URL) while the gateway shows one
     */
    public function handle(WhatsappInstance $instance): ?string
    {
        try {
            $key = (string) $instance->api_key;
            $this->record($instance, $this->gateway->session((string) $instance->session_id, $key));

            return $instance->connection_status === 'qr_ready'
                ? $this->gateway->qr((string) $instance->session_id, $key)
                : null;
        } catch (OpenWaException $exception) {
            $instance->forceFill([
                'last_error' => $exception->forSchool(),
                'last_checked_at' => now(),
            ])->save();

            return null;
        }
    }

    /**
     * Store what the gateway said about the session.
     *
     * @param  array<array-key, mixed>  $session  status, phone, pushName, lastError
     */
    public function record(WhatsappInstance $instance, array $session): void
    {
        $status = is_string($session['status'] ?? null) ? $session['status'] : $instance->connection_status;
        $ready = $status === 'ready';
        $trouble = ! in_array($status, self::RUNNING, true) && is_string($session['lastError'] ?? null) && $session['lastError'] !== ''
            ? Str::limit($session['lastError'], 250)
            : null;

        $instance->forceFill([
            'connection_status' => $status,
            'phone' => $ready && is_string($session['phone'] ?? null) ? $session['phone'] : null,
            'push_name' => $ready && is_string($session['pushName'] ?? null) ? $session['pushName'] : null,
            'connected_at' => $ready ? ($instance->connected_at ?? now()) : null,
            'last_checked_at' => now(),
            'last_error' => $trouble,
        ])->save();
    }
}
