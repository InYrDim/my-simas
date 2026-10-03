<?php

namespace Modules\Core\App\Http\Requests;

final class SemesterRequest extends MasterFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
            'start_date' => 'Tanggal mulai',
            'end_date' => 'Tanggal selesai',
        ];
    }

    /**
     * The validated fields as the action takes them.
     *
     * @return array{start_date: string, end_date: string}
     */
    public function semesterData(): array
    {
        return [
            'start_date' => (string) $this->validated('start_date'),
            'end_date' => (string) $this->validated('end_date'),
        ];
    }
}
