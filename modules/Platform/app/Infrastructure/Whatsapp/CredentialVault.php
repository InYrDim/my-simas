<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;

/**
 * Encrypts the per-school session API keys with a key of their own
 * (`OPENWA_CREDENTIALS_KEY`, 64 hex characters = 32 bytes), not APP_KEY:
 * rotating the application key leaves them readable, and a database dump
 * alone opens none of them. Without a usable key nothing is stored.
 */
final class CredentialVault
{
    private const CIPHER = 'aes-256-gcm';

    public function encrypt(#[\SensitiveParameter] string $plain): string
    {
        return $this->encrypter()->encryptString($plain);
    }

    /**
     * @throws OpenWaException when the stored value was written with another key
     */
    public function decrypt(string $payload): string
    {
        try {
            return $this->encrypter()->decryptString($payload);
        } catch (DecryptException) {
            throw new OpenWaException('API key sesi tidak bisa dibuka: OPENWA_CREDENTIALS_KEY berbeda dari saat key disimpan.');
        }
    }

    /**
     * Whether a usable credentials key is configured.
     */
    public function ready(): bool
    {
        return $this->key() !== null;
    }

    private function encrypter(): Encrypter
    {
        $key = $this->key();

        if ($key === null) {
            throw new OpenWaException('OPENWA_CREDENTIALS_KEY belum diatur atau bukan 64 karakter hex.');
        }

        return new Encrypter($key, self::CIPHER);
    }

    /**
     * The 32 raw bytes behind the configured hex string, or null.
     */
    private function key(): ?string
    {
        $hex = (string) config('services.openwa.credentials_key');

        if (strlen($hex) !== 64 || ! ctype_xdigit($hex)) {
            return null;
        }

        return (string) hex2bin($hex);
    }
}
