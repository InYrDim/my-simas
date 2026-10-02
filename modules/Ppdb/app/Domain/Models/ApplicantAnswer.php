<?php

namespace Modules\Ppdb\App\Domain\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Ppdb\Database\Factories\ApplicantAnswerFactory;

/**
 * An applicant's answer to one custom field. `value` is the text answered;
 * a checkboxes answer is a JSON list and a file answer is JSON
 * (`path`, `name`, `size`, `mime`). Plain ids, no relations.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $applicant_id
 * @property int $field_id
 * @property string|null $value
 */
#[UseFactory(ApplicantAnswerFactory::class)]
#[Fillable(['applicant_id', 'field_id', 'value'])]
class ApplicantAnswer extends Model
{
    /** @use HasFactory<ApplicantAnswerFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'ppdb_applicant_answers';
}
