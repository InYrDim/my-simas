<?php

namespace Modules\Ppdb\App\Http\Requests;

final class WaveRequest extends PpdbFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'opens_on' => ['required', 'date_format:Y-m-d'],
            'closes_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'Nama gelombang', 'opens_on' => 'Tanggal buka', 'closes_on' => 'Tanggal tutup'];
    }

    /**
     * The validated fields as the action takes them.
     *
     * @return array{name: string, opens_on: string, closes_on: string}
     */
    public function waveData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'opens_on' => (string) $this->validated('opens_on'),
            'closes_on' => (string) $this->validated('closes_on'),
        ];
    }
}
