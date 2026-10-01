<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\TeacherFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A teacher or education staff member. `user_id` optionally holds the id
 * of an Identity account — a plain column, never a relation.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $nip
 * @property string|null $nuptk
 * @property string $employment PNS|GTY|GTT|Honorer
 * @property string $duty
 * @property string|null $email
 * @property int|null $user_id
 */
#[UseFactory(TeacherFactory::class)]
#[Fillable(['name', 'nip', 'nuptk', 'employment', 'duty', 'email'])]
class Teacher extends Model
{
    /** @use HasFactory<TeacherFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return HasMany<ClassGroup, $this>
     */
    public function homeroomClasses(): HasMany
    {
        return $this->hasMany(ClassGroup::class, 'homeroom_teacher_id');
    }

    /**
     * @return HasMany<Extracurricular, $this>
     */
    public function coachedActivities(): HasMany
    {
        return $this->hasMany(Extracurricular::class, 'coach_teacher_id');
    }
}
