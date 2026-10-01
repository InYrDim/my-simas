<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\RoomFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int $capacity
 * @property string $status active|maintenance
 */
#[UseFactory(RoomFactory::class)]
#[Fillable(['code', 'name', 'type', 'capacity', 'status'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<ClassGroup, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }
}
