<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;

final class LessonAttendanceRequest extends AttendanceFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('attendance.lesson.record');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'period_slot_id' => ['required', 'integer'],
            'subject_id' => ['nullable', 'integer'],
            'marks' => ['required', 'array'],
            'marks.*.student_id' => ['required', 'integer', 'distinct'],
            'marks.*.status' => ['required', Rule::in(array_column(AttendanceStatus::forLessons(), 'value'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'class_id' => 'Kelas',
            'date' => 'Tanggal',
            'period_slot_id' => 'Jam pelajaran',
            'subject_id' => 'Mata pelajaran',
            'marks' => 'Daftar absensi',
            'marks.*.student_id' => 'Siswa',
            'marks.*.status' => 'Status',
        ];
    }

    public function subjectId(): ?int
    {
        $subject = $this->validated('subject_id');

        return $subject === null ? null : (int) $subject;
    }

    /**
     * @return array<int, AttendanceStatus> keyed by student id
     */
    public function marks(): array
    {
        $marks = [];

        foreach ($this->validated('marks') as $mark) {
            $marks[(int) $mark['student_id']] = AttendanceStatus::from($mark['status']);
        }

        return $marks;
    }
}
