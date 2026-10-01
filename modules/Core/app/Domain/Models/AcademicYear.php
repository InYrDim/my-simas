<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\App\Domain\Enums\AcademicYearStatus;
use Modules\Core\Database\Factories\AcademicYearFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $curriculum
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property AcademicYearStatus $status
 */
#[UseFactory(AcademicYearFactory::class)]
#[Fillable(['name', 'curriculum', 'start_date', 'end_date'])]
class AcademicYear extends Model
{
    /** @use HasFactory<AcademicYearFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'status' => AcademicYearStatus::class,
        ];
    }

    /**
     * @return HasMany<Semester, $this>
     */
    public function semesters(): HasMany
    {
        return $this->hasMany(Semester::class)->orderBy('start_date');
    }

    public function isActive(): bool
    {
        return $this->status === AcademicYearStatus::Active;
    }
}
