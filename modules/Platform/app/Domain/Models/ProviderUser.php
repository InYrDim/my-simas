<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\ProviderUserFactory;

/**
 * SaaS provider staff — CENTRAL identity, never tenant-scoped:
 * no tenant_id, no BelongsToTenant. Authenticates via the dedicated
 * 'provider' guard; has no access to tenant routes and vice versa
 * (guard separation is enforced by route middleware in Stage 9).
 *
 * NOT exposed to other modules: Identity owns end-user identity, this
 * model is Platform's own operational surface (Stage 7 command
 * `provider:create-user`).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(ProviderUserFactory::class)]
#[Fillable(['name', 'email', 'password'])]
class ProviderUser extends Authenticatable
{
    /** @use HasFactory<ProviderUserFactory> */
    use HasFactory;

    protected $table = 'provider_users';

    protected $hidden = ['password', 'remember_token'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
