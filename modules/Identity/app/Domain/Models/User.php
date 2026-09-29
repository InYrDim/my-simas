<?php

namespace Modules\Identity\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Platform\App\Contracts\Concerns\HasTenantRoles;

/**
 * Tenant-scoped user: the same email may exist in multiple tenants
 * (unique(tenant_id, email)). Roles/permissions resolve against the
 * CURRENT tenant via Platform's HasTenantRoles wrapper — Spatie is
 * never imported directly in this module.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
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
            'password' => 'hashed',
        ];
    }
}
