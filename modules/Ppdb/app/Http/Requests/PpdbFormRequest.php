<?php

namespace Modules\Ppdb\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base for the PPDB writes: Indonesian messages (the app locale ships
 * none). The route decides who may write; a request only validates.
 */
abstract class PpdbFormRequest extends FormRequest
{
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'max' => ':attribute terlalu panjang (maksimal :max).',
            'min' => ':attribute tidak boleh kurang dari :min.',
            'between' => ':attribute harus antara :min dan :max.',
            'integer' => ':attribute harus berupa angka bulat.',
            'date_format' => ':attribute tidak valid.',
            'in' => ':attribute tidak valid.',
            'array' => ':attribute tidak valid.',
            'distinct' => ':attribute tidak boleh ganda.',
        ];
    }
}
