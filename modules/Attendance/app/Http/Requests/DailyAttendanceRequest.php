<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Attendance\App\Domain\Enums\AttendanceStatus;

final class DailyAttendanceRequest extends AttendanceFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('attendance.daily.use');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'class_id' => ['bail', 'required', 'integer', $this->recordableClassRule()],
            'date' => ['required', 'date_format:Y-m-d'],
            'marks' => ['required', 'array'],
            'marks.*.student_id' => ['required', 'integer', 'distinct'],
            'marks.*.status' => ['required', Rule::in(AttendanceStatus::values())],
            'marks.*.note' => ['nullable', 'string', 'max:255'],
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
            'marks' => 'Daftar absensi',
            'marks.*.student_id' => 'Siswa',
            'marks.*.status' => 'Status',
            'marks.*.note' => 'Keterangan',
        ];
    }

    /**
     * @return array<int, array{status: AttendanceStatus, note: ?string}> keyed by student id
     */
    public function marks(): array
    {
        $marks = [];

        foreach ($this->validated('marks') as $mark) {
            $note = trim((string) ($mark['note'] ?? ''));

            $marks[(int) $mark['student_id']] = [
                'status' => AttendanceStatus::from($mark['status']),
                'note' => $note === '' ? null : $note,
            ];
        }

        return $marks;
    }
}
