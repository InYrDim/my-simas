<?php

namespace Modules\Ppdb\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Ppdb\App\Domain\Enums\FieldRequirement;
use Modules\Ppdb\App\Domain\Support\FormFields;

final class FormFieldsRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['fields' => ['required', 'array']];

        foreach (FormFields::keys() as $key) {
            $rules["fields.{$key}"] = ['required', Rule::in(FieldRequirement::values())];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $names = ['fields' => 'Kolom formulir'];

        foreach (FormFields::keys() as $key) {
            $names["fields.{$key}"] = FormFields::label($key);
        }

        return $names;
    }

    /**
     * The validated choices as the action takes them.
     *
     * @return array<string, string>
     */
    public function fieldsData(): array
    {
        $fields = [];

        foreach (FormFields::keys() as $key) {
            $fields[$key] = (string) $this->validated("fields.{$key}");
        }

        return $fields;
    }
}
