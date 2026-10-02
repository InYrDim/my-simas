<?php

namespace Modules\Ppdb\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Ppdb\App\Domain\Actions\DeleteFormField;
use Modules\Ppdb\App\Domain\Actions\SaveForm;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;
use Modules\Ppdb\App\Domain\Models\AdmissionPath;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Queries\ChosenPeriod;
use Modules\Ppdb\App\Domain\Support\CustomFieldRules;
use Modules\Ppdb\App\Http\Requests\SaveFormRequest;

/**
 * Formulir: the school builds the registration form of a period — every
 * field in order, the custom ones added, changed, archived or removed — and
 * sees it as an applicant will.
 */
final class FormController
{
    public function show(Request $request, ChosenPeriod $chosen): Response
    {
        $periods = $chosen->all();
        $selected = $chosen->among($periods, $request->integer('periode'));

        $fields = $selected === null ? collect() : $selected->fields()->get();
        $answered = $fields->isEmpty() ? collect() : ApplicantAnswer::query()
            ->whereIn('field_id', $fields->pluck('id')->all())
            ->distinct()
            ->pluck('field_id')
            ->flip();

        return Inertia::render('Ppdb/FormBuilder', [
            'periods' => $periods->map(fn (AdmissionPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'statusLabel' => $period->status->label(),
            ])->values()->all(),
            'selected' => $selected === null ? null : [
                'id' => $selected->id,
                'name' => $selected->name,
                'editable' => $selected->status !== PeriodStatus::Closed,
            ],
            'fields' => $fields->map(fn (FormField $field): array => [
                'id' => $field->id,
                'key' => $field->key,
                'type' => $field->type->value,
                'label' => $field->label,
                'help' => $field->help,
                'required' => $field->required,
                'archived' => $field->isArchived(),
                'locked' => $field->isLocked(),
                'options' => $field->options ?? [],
                'rules' => $field->rules ?? [],
                'hasAnswers' => $answered->has($field->id),
            ])->values()->all(),
            'paths' => $selected === null ? [] : $selected->paths->map(fn (AdmissionPath $path): array => [
                'value' => (string) $path->id,
                'label' => $path->name,
            ])->values()->all(),
            'types' => array_map(
                fn (FieldType $type): array => ['value' => $type->value, 'label' => $type->label(), 'hasOptions' => $type->hasOptions(), 'takesAnswer' => $type->takesAnswer()],
                FieldType::custom(),
            ),
            'limits' => [
                'fields' => SaveForm::MAX_FIELDS,
                'options' => SaveForm::MAX_OPTIONS,
                'fileKb' => ['min' => CustomFieldRules::MIN_FILE_KB, 'max' => CustomFieldRules::MAX_FILE_KB, 'default' => CustomFieldRules::DEFAULT_FILE_KB],
                'formats' => CustomFieldRules::FORMATS,
            ],
        ]);
    }

    public function update(SaveFormRequest $request, AdmissionPeriod $period, SaveForm $save): RedirectResponse
    {
        $save->handle($period, $request->rows());

        return back()->with('status', 'Formulir pendaftaran disimpan.');
    }

    public function destroy(FormField $field, DeleteFormField $delete): RedirectResponse
    {
        $delete->handle($field);

        return back()->with('status', 'Kolom dihapus.');
    }
}
