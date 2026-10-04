<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * One scan or one manual pick at the scanner page. The mode decides the
 * permission: the gate modes are daily attendance, the lesson mode is
 * lesson attendance — each only while the school has it switched on.
 */
final class ScanRequest extends AttendanceFormRequest
{
    public const GATE_IN = 'gate-in';

    public const GATE_OUT = 'gate-out';

    public const LESSON = 'lesson';

    public function authorize(): bool
    {
        return match ($this->input('mode')) {
            self::GATE_IN, self::GATE_OUT => Gate::allows('attendance.gate.use'),
            self::LESSON => Gate::allows('attendance.lesson.use'),
            // An unknown mode is answered by the rules, for anyone who may record at all.
            default => Gate::allows('attendance.scan.use'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in([self::GATE_IN, self::GATE_OUT, self::LESSON])],
            'token' => ['nullable', 'required_without:student_id', 'string', 'max:128'],
            'student_id' => ['nullable', 'required_without:token', 'integer'],
            'class_id' => ['bail', 'nullable', 'required_if:mode,'.self::LESSON, 'integer', $this->recordableClassRule()],
            'period_slot_id' => ['nullable', 'required_if:mode,'.self::LESSON, 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mode' => 'Mode',
            'token' => 'Kode QR',
            'student_id' => 'Siswa',
            'class_id' => 'Kelas',
            'period_slot_id' => 'Jam pelajaran',
        ];
    }

    public function mode(): string
    {
        return (string) $this->validated('mode');
    }

    public function token(): ?string
    {
        $token = trim((string) $this->validated('token'));

        return $token === '' ? null : $token;
    }
}
