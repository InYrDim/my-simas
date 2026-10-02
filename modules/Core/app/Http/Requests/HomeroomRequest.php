<?php

namespace Modules\Core\App\Http\Requests;

final class HomeroomRequest extends AcademicFormRequest
{
    /**
     * Each select sends the teacher id or the "none" sentinel; the sentinel
     * becomes null so the class loses its homeroom teacher.
     */
    protected function prepareForValidation(): void
    {
        $homerooms = $this->input('homerooms');

        if (! is_array($homerooms)) {
            return;
        }

        $this->merge(['homerooms' => array_map(
            fn (mixed $value): mixed => $value === self::NONE || $value === '' ? null : $value,
            $homerooms,
        )]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'homerooms' => ['required', 'array'],
            'homerooms.*' => ['nullable', 'integer', $this->existsInSchool('teachers')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'homerooms' => 'Penetapan wali kelas',
            'homerooms.*' => 'Guru',
        ];
    }

    /**
     * @return array<int, int|null>
     */
    public function homerooms(): array
    {
        return array_map(
            fn (mixed $value): ?int => $value === null ? null : (int) $value,
            $this->validated('homerooms'),
        );
    }
}
