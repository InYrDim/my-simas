<?php

namespace Modules\Ppdb\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Ppdb\App\Domain\Enums\PeriodStatus;

final class PeriodRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'entry_year' => ['required', 'integer', 'between:2000,2100'],
            'status' => ['required', Rule::in(PeriodStatus::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Nama periode', 'entry_year' => 'Tahun masuk', 'status' => 'Status'];
    }

    /**
     * The validated fields as the action takes them (a form sends text).
     *
     * @return array{name: string, entry_year: int, status: string}
     */
    public function periodData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'entry_year' => (int) $this->validated('entry_year'),
            'status' => (string) $this->validated('status'),
        ];
    }
}
