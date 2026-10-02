<?php

namespace Modules\Platform\App\Infrastructure\Whatsapp;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores an attribute encrypted by the CredentialVault.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class CredentialCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(CredentialVault::class)->decrypt((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, #[\SensitiveParameter] mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(CredentialVault::class)->encrypt((string) $value);
    }
}
