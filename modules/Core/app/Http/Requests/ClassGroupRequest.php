<?php

namespace Modules\Core\App\Http\Requests;

use Modules\Core\App\Domain\Models\ClassGroup;

final class ClassGroupRequest extends MasterFormRequest
{
    protected array $optionalSelects = ['major_id', 'room_id'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ClassGroup|null $class */
        $class = $this->route('classGroup');

        return [
            'academic_year_id' => ['required', 'integer', $this->existsInSchool('academic_years')],
            'grade_id' => ['required', 'integer', $this->existsInSchool('grades')],
            'major_id' => ['nullable', 'integer', $this->existsInSchool('majors')],
            'room_id' => ['nullable', 'integer', $this->existsInSchool('rooms')],
            'name' => [
                'required', 'string', 'max:32',
                $this->uniqueInSchool('classes', 'name', $class?->id)
                    ->where('academic_year_id', $this->input('academic_year_id')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'academic_year_id' => 'Tahun ajaran',
            'grade_id' => 'Tingkat',
            'major_id' => 'Jurusan',
            'room_id' => 'Ruangan',
            'name' => 'Nama kelas',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'name.unique' => 'Nama kelas ini sudah dipakai pada tahun ajaran tersebut.'];
    }
}
