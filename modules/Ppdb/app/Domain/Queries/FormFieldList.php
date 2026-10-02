<?php

namespace Modules\Ppdb\App\Domain\Queries;

use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;
use Modules\Ppdb\App\Domain\Support\AnswerFiles;

/**
 * A period's form, and an applicant's answers to it, as plain data for the
 * pages that draw the form (the applicant's own, the committee's and the
 * detail page).
 */
final class FormFieldList
{
    public function __construct(
        private readonly AnswerFiles $files,
    ) {}

    /**
     * Every field of the period in form order, archived ones marked (a page
     * draws only the asked ones, but the detail page still shows an
     * archived field's answer).
     *
     * @return list<array{id: int, key: string|null, type: string, label: string, help: string|null, required: bool, archived: bool, options: list<string>, rules: array<string, mixed>}>
     */
    public function for(AdmissionPeriod $period): array
    {
        return array_values($period->fields()->get()->map(fn (FormField $field): array => [
            'id' => $field->id,
            'key' => $field->key,
            'type' => $field->type->value,
            'label' => $field->label,
            'help' => $field->help,
            'required' => $field->required,
            'archived' => $field->isArchived(),
            'options' => $field->options ?? [],
            'rules' => $field->rules ?? [],
        ])->all());
    }

    /**
     * What the applicant answered to the custom fields: the text, or the
     * list of choices of a checkboxes field, by field id.
     *
     * @return array<int|string, string|list<string>>
     */
    public function answersOf(Applicant $applicant): array
    {
        $types = FormField::query()
            ->where('period_id', $applicant->period_id)
            ->pluck('type', 'id');

        $answers = [];

        foreach (ApplicantAnswer::query()->where('applicant_id', $applicant->id)->get() as $answer) {
            if ($answer->value === null || ! $types->has($answer->field_id)) {
                continue;
            }

            $type = $types->get($answer->field_id);

            if ($type === FieldType::File || $type === FieldType::File->value) {
                continue;
            }

            if ($type === FieldType::Checkboxes || $type === FieldType::Checkboxes->value) {
                $decoded = json_decode($answer->value, true);
                $answers[(string) $answer->field_id] = is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];

                continue;
            }

            $answers[(string) $answer->field_id] = $answer->value;
        }

        return $answers;
    }

    /**
     * The files the applicant uploaded, by field id: the name they sent
     * and where to download it.
     *
     * @param  callable(FormField): string  $url  the download address of a file field
     * @return array<int|string, array{name: string, url: string}>
     */
    public function filesOf(Applicant $applicant, callable $url): array
    {
        $files = [];

        $fields = FormField::query()
            ->where('period_id', $applicant->period_id)
            ->where('type', FieldType::File->value)
            ->get();

        foreach ($fields as $field) {
            $stored = $this->files->of($applicant, $field);

            if ($stored !== null) {
                $files[(string) $field->id] = ['name' => $stored['name'], 'url' => $url($field)];
            }
        }

        return $files;
    }
}
