<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Support\Facades\Gate;

final class CancelCheckOutRequest extends AttendanceFormRequest
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
            'student_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['student_id' => 'Siswa'];
    }
}
