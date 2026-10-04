<?php

namespace Modules\Attendance\App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Modules\Attendance\App\Domain\Queries\ClassChoices;

/**
 * Base for the attendance writes: Indonesian messages (the app locale
 * ships none). Each request names the permission it needs.
 */
abstract class AttendanceFormRequest extends FormRequest
{
    /**
     * Refuses a class the signed-in user may not record (a teacher holding
     * only the own-class permission, asking for another class).
     */
    protected function recordableClassRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $userId = Auth::id();

            if (! app(ClassChoices::class)->mayRecord((int) $value, $userId === null ? null : (int) $userId)) {
                $fail('Kelas ini bukan kelas yang Anda ajar.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'required_without' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute terlalu panjang (maksimal :max).',
            'date_format' => ':attribute tidak valid.',
            'in' => ':attribute tidak valid.',
            'integer' => ':attribute harus berupa angka bulat.',
            'array' => ':attribute tidak valid.',
            'distinct' => ':attribute tidak boleh ganda.',
        ];
    }
}
