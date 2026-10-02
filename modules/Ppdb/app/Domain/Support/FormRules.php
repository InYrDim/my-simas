<?php

namespace Modules\Ppdb\App\Domain\Support;

use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Modules\Ppdb\App\Domain\Enums\FieldType;
use Modules\Ppdb\App\Domain\Models\AdmissionPeriod;
use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Models\ApplicantAnswer;
use Modules\Ppdb\App\Domain\Models\FormField;

/**
 * The validation rules of a period's registration form, built from its
 * fields: an archived field has no rule (so a value sent for it is dropped
 * from the validated data); a required one is `required`, an optional one
 * `nullable`; the path, name and gender are always required. The answers to
 * custom fields are validated as `answers.<field id>`. A period with no
 * form (none chosen) is asked the usual one.
 */
final class FormRules
{
    /**
     * @return array<string, mixed>
     */
    public function forPeriod(?AdmissionPeriod $period, ?Applicant $applicant = null): array
    {
        $rules = [];

        foreach ($this->builtinStates($period) as $key => $state) {
            if ($state === 'off') {
                continue;
            }

            $presence = $state === 'required' ? 'required' : 'nullable';
            $rules[$key] = [$presence, ...BuiltinFields::valueRules($key)];
        }

        if ($period === null) {
            return $rules;
        }

        $uploaded = $applicant === null ? [] : $this->uploadedFieldIds($applicant);

        foreach ($this->askedCustomFields($period) as $field) {
            $rules = [...$rules, ...$this->customRules($field, in_array($field->id, $uploaded, true))];
        }

        return $rules;
    }

    /**
     * The names the messages call the fields by: the built-in labels and the
     * labels of the period's custom fields.
     *
     * @return array<string, string>
     */
    public function attributesFor(?AdmissionPeriod $period): array
    {
        $names = ['wave_id' => 'Gelombang'];

        foreach (BuiltinFields::keys() as $key) {
            $names[$key] = BuiltinFields::label($key);
        }

        if ($period !== null) {
            foreach ($this->askedCustomFields($period) as $field) {
                $names["answers.{$field->id}"] = $field->label;
                $names["answers.{$field->id}.*"] = $field->label;
            }
        }

        return $names;
    }

    /**
     * The custom fields of the period that are asked today and take an
     * answer, in form order.
     *
     * @return Collection<int, FormField>
     */
    public function askedCustomFields(AdmissionPeriod $period): Collection
    {
        return FormField::query()
            ->where('period_id', $period->id)
            ->whereNull('key')
            ->whereNull('archived_at')
            ->where('type', '!=', FieldType::Section->value)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * What each built-in field of the period's form is: `required`,
     * `optional` or `off` (archived), by key.
     *
     * @return array<string, string>
     */
    public function builtinStates(?AdmissionPeriod $period): array
    {
        $states = [];

        foreach (BuiltinFields::keys() as $key) {
            $states[$key] = BuiltinFields::isRequiredByDefault($key) ? 'required' : 'optional';
        }

        if ($period === null) {
            return $states;
        }

        $fields = FormField::query()->where('period_id', $period->id)->whereNotNull('key')->get();

        foreach ($fields as $field) {
            if ($field->key === null || ! BuiltinFields::exists($field->key)) {
                continue;
            }

            $states[$field->key] = match (true) {
                BuiltinFields::isLocked($field->key) => 'required',
                $field->isArchived() => 'off',
                $field->required => 'required',
                default => 'optional',
            };
        }

        return $states;
    }

    /**
     * @return array<string, mixed>
     */
    private function customRules(FormField $field, bool $alreadyUploaded): array
    {
        $presence = $field->required ? 'required' : 'nullable';
        $settings = $field->rules ?? [];
        $name = "answers.{$field->id}";

        return match ($field->type) {
            FieldType::Text => [$name => [$presence, 'string', ...$this->textRules($settings)]],
            FieldType::Paragraph => [$name => [$presence, 'string', 'max:'.(int) ($settings['max_length'] ?? 2000)]],
            FieldType::Number => [$name => array_values(array_filter([
                $presence,
                'numeric',
                isset($settings['min']) ? 'min:'.$settings['min'] : null,
                isset($settings['max']) ? 'max:'.$settings['max'] : null,
            ]))],
            FieldType::Date => [$name => [
                $presence,
                'date_format:Y-m-d',
                ...(($settings['allow_future'] ?? true) === false ? ['before_or_equal:today'] : []),
            ]],
            FieldType::Select => [$name => [$presence, 'string', Rule::in($field->options ?? [])]],
            FieldType::Checkboxes => [
                $name => [$presence, 'array'],
                "{$name}.*" => ['string', Rule::in($field->options ?? [])],
            ],
            FieldType::File => [$name => $this->fileRules($field, $alreadyUploaded)],
            default => [],
        };
    }

    /**
     * A file field: required only while the applicant has no file for it
     * yet; the size and kinds are the field's own.
     *
     * @return list<string>
     */
    private function fileRules(FormField $field, bool $alreadyUploaded): array
    {
        $settings = $field->rules ?? [];
        $kinds = is_array($settings['kinds'] ?? null) ? $settings['kinds'] : CustomFieldRules::KINDS;
        $extensions = [];

        foreach ($kinds as $kind) {
            $extensions = [...$extensions, ...($kind === 'pdf' ? ['pdf'] : ['jpg', 'jpeg', 'png'])];
        }

        return [
            $field->required && ! $alreadyUploaded ? 'required' : 'nullable',
            'file',
            'max:'.(int) ($settings['max_size_kb'] ?? CustomFieldRules::DEFAULT_FILE_KB),
            'mimes:'.implode(',', $extensions),
        ];
    }

    /**
     * The ids of the file fields the applicant has already uploaded to.
     *
     * @return list<int>
     */
    private function uploadedFieldIds(Applicant $applicant): array
    {
        $ids = ApplicantAnswer::query()
            ->where('applicant_id', $applicant->id)
            ->whereNotNull('value')
            ->pluck('field_id')
            ->all();

        return array_values(array_map('intval', $ids));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<string>
     */
    private function textRules(array $settings): array
    {
        $rules = ['max:'.(int) ($settings['max_length'] ?? 255)];
        $format = $settings['format'] ?? 'free';

        if ($format === 'digits') {
            $rules[] = 'regex:/^[0-9]+$/';
        } elseif ($format === 'email') {
            $rules[] = 'email';
        } elseif ($format === 'phone') {
            $rules[] = 'regex:/^\+?[0-9\s\-()]{6,20}$/';
        }

        return $rules;
    }
}
