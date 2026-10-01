<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;

final class ExtracurricularRequest extends MasterFormRequest
{
    protected array $optionalSelects = ['coach_teacher_id'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'coach_teacher_id' => ['nullable', 'integer', $this->existsInSchool('teachers')],
            'schedule' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', Rule::in(['Wajib', 'Pilihan'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama kegiatan',
            'coach_teacher_id' => 'Pembina',
            'schedule' => 'Jadwal',
            'kind' => 'Jenis',
        ];
    }
}
