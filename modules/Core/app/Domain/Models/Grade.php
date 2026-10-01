<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\GradeFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A grade level (tingkat). The set follows the school's jenjang and is
 * seeded from it, never edited by hand.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property int $sort_order
 */
#[UseFactory(GradeFactory::class)]
#[Fillable(['name', 'sort_order'])]
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<ClassGroup, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }
}
