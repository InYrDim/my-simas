<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\MajorFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A major: peminatan (SMA) or kompetensi keahlian (SMK).
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $code
 * @property string $name
 * @property string $kind
 * @property list<string>|null $concentrations
 */
#[UseFactory(MajorFactory::class)]
#[Fillable(['code', 'name', 'kind', 'concentrations'])]
class Major extends Model
{
    /** @use HasFactory<MajorFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['concentrations' => 'array'];
    }

    /**
     * @return HasMany<ClassGroup, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassGroup::class);
    }
}
