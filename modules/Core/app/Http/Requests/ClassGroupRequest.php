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

    /**
     * The validated fields as the action takes them; optional fields are
     * present only when the form sent them.
     *
     * @return array{academic_year_id: int, grade_id: int, major_id?: int|null, room_id?: int|null, name: string}
     */
    public function classData(): array
    {
        $validated = $this->validated();

        $data = [
            'academic_year_id' => (int) $validated['academic_year_id'],
            'grade_id' => (int) $validated['grade_id'],
            'name' => (string) $validated['name'],
        ];

        if (array_key_exists('major_id', $validated)) {
            $data['major_id'] = $validated['major_id'] === null ? null : (int) $validated['major_id'];
        }

        if (array_key_exists('room_id', $validated)) {
            $data['room_id'] = $validated['room_id'] === null ? null : (int) $validated['room_id'];
        }

        return $data;
    }
}
