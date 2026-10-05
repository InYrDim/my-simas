<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Support\Facades\Gate;

/**
 * The todo checkbox of Jadwal Hari Ini: one of the teacher's lessons of
 * today and the state it is put in.
 */
final class LessonCheckRequest extends AttendanceFormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('attendance.class.record');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_slot_id' => ['required', 'integer'],
            'checked' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['period_slot_id' => 'Jam pelajaran', 'checked' => 'Tanda selesai'];
    }
}
