<?php

namespace Modules\Ppdb\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Ppdb\App\Domain\Enums\Decision;

/**
 * The scores and decisions of one path, as the selection page sends them.
 */
final class SelectionRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'path_id' => ['required', 'integer'],
            'rows' => ['required', 'array', 'min:1', 'max:2000'],
            'rows.*.applicant_id' => ['required', 'integer', 'distinct'],
            'rows.*.score' => ['nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'rows.*.decision' => ['required', Rule::in(Decision::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'path_id' => 'Jalur',
            'rows' => 'Daftar pendaftar',
            'rows.*.applicant_id' => 'Pendaftar',
            'rows.*.score' => 'Nilai',
            'rows.*.decision' => 'Keputusan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'numeric' => ':attribute harus berupa angka.',
            'decimal' => ':attribute paling banyak dua angka di belakang koma.',
            'rows.*.score.between' => 'Nilai harus antara 0 dan 100.',
        ];
    }
}
