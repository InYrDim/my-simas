<?php

namespace Modules\Platform\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Platform\Database\Factories\TenantModulesFactory;

/**
 * Internal Platform model for per-tenant module flags — never exposed
 * to other modules (consumers use TenantModules contract).
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $module
 * @property bool $enabled
 * @property Carbon|null $enabled_at
 * @property Carbon|null $expires_at
 * @property array<string, mixed>|null $meta
 * @property string $source `plan` (set by a subscription) or `manual` (set by the provider)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(TenantModulesFactory::class)]
#[Fillable(['module', 'enabled', 'enabled_at', 'expires_at', 'meta', 'source'])]
class TenantModules extends Model
{
    /** @use HasFactory<TenantModulesFactory> */
    use BelongsToTenant, HasFactory;

    public const SOURCE_PLAN = 'plan';

    public const SOURCE_MANUAL = 'manual';

    protected $table = 'tenant_modules';

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'expires_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
