<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Models\PeriodSlot;

final class PeriodSlotRequest extends AcademicFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'day' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::in(PeriodSlot::TYPES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'day' => 'Hari',
            'start_time' => 'Jam mulai',
            'end_time' => 'Jam selesai',
            'type' => 'Jenis',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'after' => ':attribute harus setelah jam mulai.',
            'date_format' => ':attribute harus berformat jj:mm.',
        ];
    }

    /**
     * @return array{day: int, start_time: string, end_time: string, type: string}
     */
    public function slotData(): array
    {
        return [
            'day' => (int) $this->validated('day'),
            'start_time' => (string) $this->validated('start_time'),
            'end_time' => (string) $this->validated('end_time'),
            'type' => (string) $this->validated('type'),
        ];
    }
}
