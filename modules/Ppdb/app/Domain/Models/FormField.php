<?php

namespace Modules\Ppdb\App\Domain\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Platform\App\Contracts\Concerns\BelongsToTenant;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Support\BuiltinFields;
use Modules\Ppdb\Database\Factories\FormFieldFactory;

/**
 * One field of a period's registration form: a built-in field of the
 * applicant record (`key` is its column) or a custom one (`key` empty). The
 * rows of a period, in `sort_order`, are the form. An archived field is no
 * longer asked, but what applicants answered stays.
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $period_id
 * @property string|null $key
 * @property FieldType $type
 * @property string $label
 * @property string|null $help
 * @property bool $required
 * @property list<string>|null $options the choices of a select or checkboxes field
 * @property array<string, mixed>|null $rules the basic validation of a custom field
 * @property int $sort_order
 * @property CarbonInterface|null $archived_at
 */
#[UseFactory(FormFieldFactory::class)]
#[Fillable(['period_id', 'key', 'type', 'label', 'help', 'required', 'options', 'rules', 'sort_order', 'archived_at'])]
class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'ppdb_form_fields';

    public function isBuiltin(): bool
    {
        return $this->type === FieldType::Builtin;
    }

    /**
     * The built-in fields selection and re-registration depend on: always
     * asked, always required.
     */
    public function isLocked(): bool
    {
        return $this->isBuiltin() && $this->key !== null && BuiltinFields::isLocked($this->key);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Asked on the form today: not archived.
     */
    public function isAsked(): bool
    {
        return ! $this->isArchived();
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => FieldType::class,
            'required' => 'boolean',
            'options' => 'array',
            'rules' => 'array',
            'sort_order' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
