<?php

namespace Modules\Core\App\Http\Requests;

use Modules\Core\App\Domain\Models\AcademicYear;

final class AcademicYearRequest extends MasterFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AcademicYear|null $year */
        $year = $this->route('year');

        return [
            'name' => [
                'required', 'string', 'max:16', 'regex:/^\d{4}\/\d{4}$/',
                $this->uniqueInSchool('academic_years', 'name', $year?->id),
            ],
            'curriculum' => ['required', 'string', 'max:64'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'Nama tahun ajaran',
            'curriculum' => 'Kurikulum',
            'start_date' => 'Tanggal mulai',
            'end_date' => 'Tanggal selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'name.regex' => 'Nama tahun ajaran harus berformat 2026/2027.',
            'name.unique' => 'Tahun ajaran ini sudah ada.',
        ];
    }
}
