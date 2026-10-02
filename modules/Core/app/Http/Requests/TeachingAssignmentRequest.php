<?php

namespace Modules\Core\App\Http\Requests;

final class TeachingAssignmentRequest extends AcademicFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['present', 'array'],
            'assignments.*.subject_id' => ['required', 'integer', 'distinct', $this->existsInSchool('subjects')],
            'assignments.*.teacher_id' => ['required', 'integer', $this->existsInSchool('teachers')],
            'assignments.*.hours' => ['required', 'integer', 'between:1,20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'assignments' => 'Pengampu mata pelajaran',
            'assignments.*.subject_id' => 'Mata pelajaran',
            'assignments.*.teacher_id' => 'Guru',
            'assignments.*.hours' => 'JP per minggu',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'present' => ':attribute wajib dikirim.', 'distinct' => ':attribute dipilih lebih dari sekali.'];
    }

    /**
     * @return list<array{subject_id: int, teacher_id: int, hours: int}>
     */
    public function rows(): array
    {
        return array_map(fn (array $row): array => [
            'subject_id' => (int) $row['subject_id'],
            'teacher_id' => (int) $row['teacher_id'],
            'hours' => (int) $row['hours'],
        ], array_values($this->validated('assignments')));
    }
}
