<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Database\Factories\TimetableEntryFactory;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * One lesson of a class weekly timetable: a subject in a lesson slot of
 * the bell schedule. The teacher is whoever the class teaching
 * assignment names for the subject.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $period_slot_id
 * @property int $class_id
 * @property int $subject_id
 */
#[UseFactory(TimetableEntryFactory::class)]
#[Fillable(['period_slot_id', 'class_id', 'subject_id'])]
class TimetableEntry extends Model
{
    /** @use HasFactory<TimetableEntryFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<PeriodSlot, $this>
     */
    public function periodSlot(): BelongsTo
    {
        return $this->belongsTo(PeriodSlot::class);
    }

    /**
     * @return BelongsTo<ClassGroup, $this>
     */
    public function classGroup(): BelongsTo
    {
        return $this->belongsTo(ClassGroup::class, 'class_id');
    }

    /**
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
