<?php

namespace Modules\Attendance\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for the attendance writes: Indonesian messages (the app locale
 * ships none). Each request names the permission it needs.
 */
abstract class AttendanceFormRequest extends FormRequest
{
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
