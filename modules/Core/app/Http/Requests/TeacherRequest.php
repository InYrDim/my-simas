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

    /**
     * The validated fields as the action takes them; optional fields are
     * present only when the form sent them.
     *
     * @return array{name: string, nip?: string|null, nuptk?: string|null, employment: string, duty: string, email?: string|null}
     */
    public function teacherData(): array
    {
        $validated = $this->validated();

        $data = [
            'name' => (string) $validated['name'],
            'employment' => (string) $validated['employment'],
            'duty' => (string) $validated['duty'],
        ];

        if (array_key_exists('nip', $validated)) {
            $data['nip'] = $validated['nip'] === null ? null : (string) $validated['nip'];
        }

        if (array_key_exists('nuptk', $validated)) {
            $data['nuptk'] = $validated['nuptk'] === null ? null : (string) $validated['nuptk'];
        }

        if (array_key_exists('email', $validated)) {
            $data['email'] = $validated['email'] === null ? null : (string) $validated['email'];
        }

        return $data;
    }
}
