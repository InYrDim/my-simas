<?php

namespace Modules\Core\App\Http\Requests;

final class TimetableEntryRequest extends AcademicFormRequest
{
    /** @var list<string> */
    protected array $optionalSelects = ['subject_id'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_slot_id' => ['required', 'integer'],
            'class_id' => ['required', 'integer'],
            'subject_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'period_slot_id' => 'Jam pelajaran',
            'class_id' => 'Kelas',
            'subject_id' => 'Mata pelajaran',
        ];
    }

    public function periodSlotId(): int
    {
        return (int) $this->validated('period_slot_id');
    }

    public function classId(): int
    {
        return (int) $this->validated('class_id');
    }

    public function subjectId(): ?int
    {
        $subject = $this->validated('subject_id');

        return $subject === null ? null : (int) $subject;
    }
}
