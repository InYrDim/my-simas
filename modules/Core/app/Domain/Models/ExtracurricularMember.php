<?php

namespace Modules\Core\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;

/**
 * Membership of a student in an extracurricular. A plain model rather than
 * a pivot: attach() bypasses model events, and `tenant_id` must be filled
 * by the BelongsToTenant creating hook.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $extracurricular_id
 * @property int $student_id
 */
#[Fillable(['extracurricular_id', 'student_id'])]
class ExtracurricularMember extends Model
{
    use BelongsToTenant;

    protected $table = 'extracurricular_members';
}
