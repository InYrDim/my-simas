<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Models\Teacher;

final class TeacherRequest extends MasterFormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['nip', 'nuptk', 'email'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Teacher|null $teacher */
        $teacher = $this->route('teacher');

        return [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:32', $this->uniqueInSchool('teachers', 'nip', $teacher?->id)],
            'nuptk' => ['nullable', 'string', 'max:32'],
            'employment' => ['required', Rule::in(['PNS', 'GTY', 'GTT', 'Honorer'])],
            'duty' => ['required', Rule::in(['Guru Mapel', 'Tenaga Kependidikan', 'Kepala Sekolah'])],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'nip' => 'NIP',
            'nuptk' => 'NUPTK',
            'employment' => 'Status kepegawaian',
            'duty' => 'Tugas',
            'email' => 'Email',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'nip.unique' => 'NIP ini sudah terdaftar.'];
    }
}
