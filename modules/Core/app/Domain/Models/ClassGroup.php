<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Database\Factories\ClassGroupFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * A class group (rombel) of one academic year. Stored in `classes`.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $academic_year_id
 * @property int $grade_id
 * @property int|null $major_id
 * @property int|null $room_id
 * @property int|null $homeroom_teacher_id
 * @property string $name
 */
#[UseFactory(ClassGroupFactory::class)]
#[Fillable(['academic_year_id', 'grade_id', 'major_id', 'room_id', 'name'])]
class ClassGroup extends Model
{
    /** @use HasFactory<ClassGroupFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'classes';

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<Major, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function homeroom(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'homeroom_teacher_id');
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'class_id');
    }
}
