<?php

namespace Modules\Ppdb\App\Domain\Actions;

use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\FormField;

/**
 * Gives a new period the form of an earlier one: every built-in field in the
 * state and place it has there, and the custom fields that are still asked
 * (an archived custom field is left behind). The new period has no
 * applicants yet, so whatever form it was seeded with is replaced.
 */
final class CopyFormFields
{
    public function handle(AdmissionPeriod $from, AdmissionPeriod $to): void
    {
        FormField::query()->where('period_id', $to->id)->delete();

        $fields = FormField::query()
            ->where('period_id', $from->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->reject(fn (FormField $field): bool => ! $field->isBuiltin() && $field->isArchived());

        foreach ($fields as $field) {
            FormField::query()->create([
                'period_id' => $to->id,
                'key' => $field->key,
                'type' => $field->type,
                'label' => $field->label,
                'help' => $field->help,
                'required' => $field->required,
                'options' => $field->options,
                'rules' => $field->rules,
                'sort_order' => $field->sort_order,
                'archived_at' => $field->archived_at,
            ]);
        }
    }
}
