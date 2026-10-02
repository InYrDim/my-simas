<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Support\BuiltinFields;

/**
 * Gives a new period the usual form: the ten built-in fields in their usual
 * order and state. Run when a period is created, so every period has a form.
 */
final class SeedFormFields
{
    public function handle(AdmissionPeriod $period): void
    {
        foreach (BuiltinFields::keys() as $order => $key) {
            FormField::query()->create([
                'period_id' => $period->id,
                'key' => $key,
                'type' => FieldType::Builtin,
                'label' => BuiltinFields::label($key),
                'required' => BuiltinFields::isRequiredByDefault($key),
                'sort_order' => $order,
            ]);
        }
    }
}
