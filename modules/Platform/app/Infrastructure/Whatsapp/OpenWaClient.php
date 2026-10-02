<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The OpenWA gateway's REST API (https://docs.open-wa.org), as far as
 * SIMAS uses it. Two kinds of key travel in `X-API-Key`:
 *
 *  - the ADMIN key (`services.openwa.admin_api_key`) creates and deletes
 *    sessions and API keys;
 *  - a SESSION key, minted per school and scoped to its one session,
 *    starts, links, reads and sends.
 *
 * Every failure becomes an OpenWaException that carries the call and the
 * status, nothing else.
 */
final class OpenWaClient
{
    /**
     * Register a session (one linked WhatsApp number). It is not started.
     *
     * @return array<string, mixed> the session: id, name, status, ...
     */
    public function createSession(string $name): array
    {
        return $this->json($this->send($this->adminKey(), 'post', 'sessions', ['name' => $name]));
    }

    /**
     * The session with exactly this name, when the gateway has one.
     *
     * @return array<string, mixed>|null
     */
    public function findSessionByName(string $name): ?array
    {
        $sessions = $this->json($this->send($this->adminKey(), 'get', 'sessions', ['name' => $name]));

        foreach ($sessions as $session) {
            if (is_array($session) && ($session['name'] ?? null) === $name) {
                return $session;
            }
        }

        return null;
    }

    /**
     * Remove the session with its history and its linked-device credentials.
     */
    public function deleteSession(string $sessionId): void
    {
        $this->send($this->adminKey(), 'delete', "sessions/{$sessionId}");
    }

    /**
     * Mint an operator key that works for this one session only. The
     * plaintext is in the answer once and cannot be read again.
     *
     * @return array{id: string, apiKey: string}
     */
    public function createSessionKey(string $name, string $sessionId): array
    {
        $key = $this->json($this->send($this->adminKey(), 'post', 'auth/api-keys', [
            'name' => $name,
            'role' => 'operator',
            'allowedSessions' => [$sessionId],
        ]));

        if (! is_string($key['id'] ?? null) || ! is_string($key['apiKey'] ?? null)) {
            throw new OpenWaException('OpenWA tidak mengembalikan API key untuk sesi ini.');
        }

        return ['id' => $key['id'], 'apiKey' => $key['apiKey']];
    }

    public function revokeKey(string $keyId): void
    {
        $this->send($this->adminKey(), 'post', "auth/api-keys/{$keyId}/revoke");
    }

    /**
     * Boot the session's engine. A session that is already running
     * answers 400.
     *
     * @return array<string, mixed>
     */
    public function startSession(string $sessionId, #[\SensitiveParameter] string $sessionKey): array
    {
        return $this->json($this->send($sessionKey, 'post', "sessions/{$sessionId}/start"));
    }

    /**
     * Stop the engine and keep the linked device: a later start
     * reconnects without a QR. Stopping a stopped session is fine.
     *
     * @return array<string, mixed>
     */
    public function stopSession(string $sessionId, #[\SensitiveParameter] string $sessionKey): array
    {
        return $this->json($this->send($sessionKey, 'post', "sessions/{$sessionId}/stop"));
    }

    /**
     * Unlink the WhatsApp account: the next start needs a fresh QR. A
     * session that is not started answers 400.
     */
    public function logoutSession(string $sessionId, #[\SensitiveParameter] string $sessionKey): void
    {
        $this->send($sessionKey, 'post', "sessions/{$sessionId}/logout");
    }

    /**
     * @return array<string, mixed> status, phone, pushName, lastError, ...
     */
    public function session(string $sessionId, #[\SensitiveParameter] string $sessionKey): array
    {
        return $this->json($this->send($sessionKey, 'get', "sessions/{$sessionId}"));
    }

    /**
     * The QR to scan as a PNG data URL, or null while the session has
     * none to show (not at `qr_ready`: the gateway answers 400).
     */
    public function qr(string $sessionId, #[\SensitiveParameter] string $sessionKey): ?string
    {
        try {
            $qr = $this->json($this->send($sessionKey, 'get', "sessions/{$sessionId}/qr"));
        } catch (OpenWaException $exception) {
            if ($exception->status === 400) {
                return null;
            }

            throw $exception;
        }

        return is_string($qr['qrCode'] ?? null) ? $qr['qrCode'] : null;
    }

    /**
     * Hand a text message to WhatsApp. The answer means "accepted", not
     * "delivered".
     *
     * @param  string  $chatId  the number in international format plus `@c.us`
     * @return array{messageId: string|null}
     */
    public function sendText(string $sessionId, #[\SensitiveParameter] string $sessionKey, string $chatId, string $text): array
    {
        $sent = $this->json($this->send($sessionKey, 'post', "sessions/{$sessionId}/messages/send-text", [
            'chatId' => $chatId,
            'text' => $text,
        ]));

        return ['messageId' => is_string($sent['messageId'] ?? null) ? $sent['messageId'] : null];
    }

    /**
     * @param  array<string, mixed>  $data  query for GET, JSON body otherwise
     */
    private function send(#[\SensitiveParameter] string $key, string $method, string $path, array $data = []): Response
    {
        $method = strtoupper($method);
        $call = "{$method} /api/{$path}";

        try {
            $response = $this->request($key)->send($method, $path, $method === 'GET'
                ? ['query' => $data]
                : ($data === [] ? [] : ['json' => $data]));
        } catch (ConnectionException) {
            throw new OpenWaException("OpenWA tidak bisa dihubungi ({$call}).");
        }

        if ($response->failed()) {
            throw new OpenWaException("OpenWA menjawab {$response->status()} untuk {$call}.", $response->status());
        }

        return $response;
    }

    private function request(#[\SensitiveParameter] string $key): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.openwa.base_url'), '/');

        if ($baseUrl === '') {
            throw new OpenWaException('OpenWA belum dikonfigurasi: OPENWA_API_BASE_URL kosong.');
        }

        return Http::baseUrl($baseUrl.'/api')
            ->withHeaders(['X-API-Key' => $key])
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.openwa.timeout', 15));
    }

    private function adminKey(): string
    {
        $key = (string) config('services.openwa.admin_api_key');

        if ($key === '') {
            throw new OpenWaException('OpenWA belum dikonfigurasi: OPENWA_ADMIN_API_KEY kosong.');
        }

        return $key;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function json(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $data : [];
    }
}
