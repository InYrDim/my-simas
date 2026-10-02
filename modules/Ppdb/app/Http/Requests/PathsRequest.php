<?php

namespace Modules\Ppdb\App\Http\Requests;

final class PathsRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'paths' => ['required', 'array', 'min:1', 'max:20'],
            'paths.*.id' => ['nullable', 'integer'],
            'paths.*.name' => ['required', 'string', 'max:64'],
            'paths.*.quota' => ['required', 'integer', 'between:0,100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['paths' => 'Jalur', 'paths.*.name' => 'Nama jalur', 'paths.*.quota' => 'Kuota'];
    }
}
