<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Core\Database\Factories\StudentFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A student. `class_id` is the class of the current period; only an
 * active student has one. `user_id` optionally holds an Identity account
 * id — a plain column, never a relation.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string $nis
 * @property string|null $nisn
 * @property string $gender L|P
 * @property Carbon|null $birth_date
 * @property string|null $guardian_name
 * @property string|null $guardian_phone
 * @property string $status active|graduated|transferred|left
 * @property int|null $class_id
 * @property int|null $user_id
 */
#[UseFactory(StudentFactory::class)]
#[Fillable(['name', 'nis', 'nisn', 'gender', 'birth_date', 'guardian_name', 'guardian_phone', 'status', 'class_id'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['birth_date' => 'date:Y-m-d'];
    }

    /**
     * @return BelongsTo<ClassGroup, $this>
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }

    /**
     * @return HasMany<StudentClassHistory, $this>
     */
    public function classHistory(): HasMany
    {
        return $this->hasMany(StudentClassHistory::class)->orderByDesc('academic_year_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
