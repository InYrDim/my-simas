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

    /**
     * The validated fields as the action takes them; optional fields are
     * present only when the form sent them.
     *
     * @return array{name: string, coach_teacher_id?: int|null, schedule?: string|null, kind: string}
     */
    public function activityData(): array
    {
        $validated = $this->validated();

        $data = [
            'name' => (string) $validated['name'],
            'kind' => (string) $validated['kind'],
        ];

        if (array_key_exists('coach_teacher_id', $validated)) {
            $data['coach_teacher_id'] = $validated['coach_teacher_id'] === null ? null : (int) $validated['coach_teacher_id'];
        }

        if (array_key_exists('schedule', $validated)) {
            $data['schedule'] = $validated['schedule'] === null ? null : (string) $validated['schedule'];
        }

        return $data;
    }
}
