<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\ExtracurricularFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property int|null $coach_teacher_id
 * @property string|null $schedule
 * @property string $kind Wajib|Pilihan
 */
#[UseFactory(ExtracurricularFactory::class)]
#[Fillable(['name', 'coach_teacher_id', 'schedule', 'kind'])]
class Extracurricular extends Model
{
    /** @use HasFactory<ExtracurricularFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'coach_teacher_id');
    }

    /**
     * @return HasMany<ExtracurricularMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ExtracurricularMember::class);
    }

    /**
     * @return Builder<Student>
     */
    public function members(): Builder
    {
        return Student::query()->whereIn('id', $this->memberships()->select('student_id'));
    }
}
