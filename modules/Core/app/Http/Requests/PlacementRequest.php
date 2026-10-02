<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Enums\PlacementAction;

final class PlacementRequest extends AcademicFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::enum(PlacementAction::class)],
            'source_class_id' => ['required', 'integer', $this->existsInSchool('classes')],
            'target_class_id' => ['nullable', 'required_unless:action,graduate', 'integer', $this->existsInSchool('classes')],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'distinct', $this->existsInSchool('students')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'action' => 'Tindakan',
            'source_class_id' => 'Rombel asal',
            'target_class_id' => 'Rombel tujuan',
            'student_ids' => 'Siswa',
            'student_ids.*' => 'Siswa',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'required_unless' => ':attribute wajib diisi.', 'distinct' => ':attribute dipilih lebih dari sekali.', 'enum' => ':attribute tidak valid.'];
    }

    public function placementAction(): PlacementAction
    {
        return PlacementAction::from($this->validated('action'));
    }

    /**
     * @return list<int>
     */
    public function studentIds(): array
    {
        return array_values(array_map('intval', $this->validated('student_ids')));
    }
}
