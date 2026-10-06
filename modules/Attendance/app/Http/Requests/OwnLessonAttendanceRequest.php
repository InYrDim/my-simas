<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;

/**
 * A teacher saves one of their own lessons; the class and the subject
 * come from the timetable, so only the day, the slot and the marks are
 * asked. The route and this request both want the lesson permission
 * with the school's lesson switch on.
 */
final class OwnLessonAttendanceRequest extends AttendanceFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('attendance.class.lesson.use');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'period_slot_id' => ['required', 'integer'],
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
            'date' => 'Tanggal',
            'period_slot_id' => 'Jam pelajaran',
            'marks' => 'Daftar absensi',
            'marks.*.student_id' => 'Siswa',
            'marks.*.status' => 'Status',
        ];
    }

    public function day(): string
    {
        return (string) $this->validated('date');
    }

    public function slotId(): int
    {
        return (int) $this->validated('period_slot_id');
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
