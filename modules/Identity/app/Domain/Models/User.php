<?php

namespace Modules\Identity\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Modules\Identity\App\Infrastructure\Mail\ResetPasswordMail;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Platform\App\Contracts\Concerns\HasTenantRoles;
use Modules\Platform\App\Contracts\TenantUrl;

/**
 * Tenant-scoped user: the same email may exist in multiple tenants
 * (unique(tenant_id, email)). Roles/permissions resolve against the
 * CURRENT tenant via Platform's HasTenantRoles wrapper — Spatie is
 * never imported directly in this module.
 *
 * Invited users (and the provisioned first school admin) start with a
 * NULL password and activate via a set-password link; deactivated
 * users keep their row and roles but are refused at login and their
 * live sessions are ended by middleware attribute enforcement.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property Carbon|null $deactivated_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(UserFactory::class)]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasFactory, HasTenantRoles, Notifiable;

    /**
     * Spatie guard for role checks. Explicit because permission work
     * can happen outside a request (queued jobs, tenant:run) where the
     * default guard cannot be inferred from the request.
     */
    protected ?string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Whether the account is currently usable (never deactivated).
     * Deactivation never deletes the row or its roles.
     */
    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    /**
     * Tenant-aware reset link delivery. Called by the password broker
     * (Fase 2: queued Mailable directly — no Notification machinery).
     *
     * Eligibility gate: deactivated users and users without a password
     * mechanism mismatch (never set) get NO email — but the controller
     * responds generically either way, so the skip is invisible.
     * Also overrides the broker's URL with the tenant host from
     * Platform's TenantUrl contract (queue-safe: no request root).
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        if (! $this->isActive() || $this->password === null) {
            return;
        }

        $url = app(TenantUrl::class)->url($this->tenant_id, 'reset-password', [
            'token' => $token,
            'email' => $this->email,
        ]);

        Mail::to($this->email)
            ->queue(new ResetPasswordMail($url));
    }
}
