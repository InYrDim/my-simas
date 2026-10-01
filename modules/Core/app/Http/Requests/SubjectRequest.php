<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Models\Subject;

final class SubjectRequest extends MasterFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Subject|null $subject */
        $subject = $this->route('subject');

        return [
            'code' => ['required', 'string', 'max:16', $this->uniqueInSchool('subjects', 'code', $subject?->id)],
            'name' => ['required', 'string', 'max:255'],
            'group' => ['required', Rule::in(['Umum', 'Peminatan', 'Muatan Lokal', 'Kejuruan'])],
            'kkm' => ['required', 'integer', 'between:0,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'Kode',
            'name' => 'Nama',
            'group' => 'Kelompok',
            'kkm' => 'KKM',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'code.unique' => 'Kode mata pelajaran ini sudah dipakai.'];
    }
}
