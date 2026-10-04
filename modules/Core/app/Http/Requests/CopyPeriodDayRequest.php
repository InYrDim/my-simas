<?php

namespace Modules\Core\App\Http\Requests;

final class CopyPeriodDayRequest extends AcademicFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_day' => ['required', 'integer', 'between:1,7'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_day' => 'Hari asal',
            'days' => 'Hari tujuan',
            'days.*' => 'Hari tujuan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'distinct' => ':attribute dipilih lebih dari sekali.'];
    }

    /**
     * @return list<int>
     */
    public function targetDays(): array
    {
        return array_values(array_map('intval', $this->validated('days')));
    }
}
