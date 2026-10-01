<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Models\Student;

final class StudentRequest extends MasterFormRequest
{
    protected array $optionalSelects = ['class_id'];

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        foreach (['nisn', 'birth_date', 'guardian_name', 'guardian_phone'] as $field) {
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
        /** @var Student|null $student */
        $student = $this->route('student');

        return [
            'name' => ['required', 'string', 'max:255'],
            'nis' => ['required', 'string', 'max:32', $this->uniqueInSchool('students', 'nis', $student?->id)],
            'nisn' => ['nullable', 'string', 'max:32', $this->uniqueInSchool('students', 'nisn', $student?->id)],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', Rule::in(['active', 'graduated', 'transferred', 'left'])],
            'class_id' => ['nullable', 'integer', $this->existsInSchool('classes')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama',
            'nis' => 'NIS',
            'nisn' => 'NISN',
            'gender' => 'Jenis kelamin',
            'birth_date' => 'Tanggal lahir',
            'guardian_name' => 'Nama wali',
            'guardian_phone' => 'Telepon wali',
            'status' => 'Status',
            'class_id' => 'Kelas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'nis.unique' => 'NIS ini sudah terdaftar.',
            'nisn.unique' => 'NISN ini sudah terdaftar.',
        ];
    }
}
