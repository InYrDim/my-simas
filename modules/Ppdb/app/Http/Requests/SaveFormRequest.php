<?php

namespace Modules\Ppdb\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Ppdb\App\Domain\Actions\SaveForm;
use Modules\Ppdb\App\Domain\Enums\FieldType;

/**
 * The whole form of a period, in order. The shape is checked here; what the
 * rows mean (ownership, locked fields, options, ranges) is `SaveForm`'s.
 */
final class SaveFormRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'min:1', 'max:'.SaveForm::MAX_FIELDS],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.type' => ['required', Rule::in(array_map(fn (FieldType $type): string => $type->value, FieldType::cases()))],
            'fields.*.label' => ['nullable', 'string', 'max:150'],
            'fields.*.help' => ['nullable', 'string', 'max:300'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.archived' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:'.SaveForm::MAX_OPTIONS],
            'fields.*.options.*' => ['nullable', 'string', 'max:100'],
            'fields.*.rules' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fields' => 'Formulir',
            'fields.*.label' => 'Label',
            'fields.*.help' => 'Teks bantuan',
            'fields.*.options' => 'Opsi jawaban',
            'fields.*.options.*' => 'Opsi jawaban',
        ];
    }

    /**
     * The rows as the action takes them.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($this->validated('fields'));

        return $rows;
    }
}
