<?php

namespace Modules\Identity\App\Domain\Actions;

use Modules\Identity\App\Domain\Models\User;

/**
 * Direct-create a tenant user from the school-admin UI (Fase 2 Stage
 * 9). Runs inside the CURRENT tenant (BelongsToTenant stamps
 * tenant_id; fail closed without context).
 *
 * Direct-create marks the email verified: the data was entered by a
 * trusted admin, not typed by the user. Invited users (password null)
 * verify at set-password instead.
 */
final class CreateUser
{
    /**
     * @param  array{name?: string, email?: string, password?: string}  $data
     * @return User The created user, persisted.
     */
    public function handle(array $data, ?string $roleName = null): User
    {
        $user = User::query()->create([
            'name' => (string) ($data['name'] ?? ''),
            'email' => (string) ($data['email'] ?? ''),
            'password' => (string) ($data['password'] ?? ''),
        ]);

        // Not in $fillable (verification is normally EARNED via the
        // set-password link) — direct-create stamps it explicitly.
        $user->forceFill(['email_verified_at' => now()])->save();

        if ($roleName !== null) {
            $user->assignTenantRole($roleName);
        }

        return $user;
    }
}
