<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Database\Factories\StudentClassHistoryFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * One row per student and academic year. The class name is a snapshot, so
 * the history reads the same after a class is renamed or removed.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $student_id
 * @property int $academic_year_id
 * @property int|null $class_id
 * @property string $class_name
 * @property string $note
 */
#[UseFactory(StudentClassHistoryFactory::class)]
#[Fillable(['student_id', 'academic_year_id', 'class_id', 'class_name', 'note'])]
class StudentClassHistory extends Model
{
    /** @use HasFactory<StudentClassHistoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'student_class_history';

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
