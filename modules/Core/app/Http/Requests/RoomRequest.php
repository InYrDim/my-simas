<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Models\Room;

final class RoomRequest extends MasterFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Room|null $room */
        $room = $this->route('room');

        return [
            'code' => ['required', 'string', 'max:16', $this->uniqueInSchool('rooms', 'code', $room?->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['Kelas', 'Laboratorium', 'Aula', 'Perpustakaan', 'Lainnya'])],
            'capacity' => ['required', 'integer', 'between:1,2000'],
            'status' => ['sometimes', Rule::in(['active', 'maintenance'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'Kode',
            'name' => 'Nama ruangan',
            'type' => 'Jenis',
            'capacity' => 'Kapasitas',
            'status' => 'Status',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [...parent::messages(), 'code.unique' => 'Kode ruangan ini sudah dipakai.'];
    }
}
