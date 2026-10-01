<?php

namespace Modules\Core\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\App\Domain\Enums\SchoolLevel;

final class SchoolProfileRequest extends MasterFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'level' => ['required', Rule::enum(SchoolLevel::class)],
            'npsn' => ['nullable', 'string', 'max:16'],
            'ownership' => ['nullable', Rule::in(['Negeri', 'Swasta'])],
            'accreditation' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'headmaster' => ['nullable', 'string', 'max:255'],
            'headmaster_nip' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'level' => 'Jenjang',
            'npsn' => 'NPSN',
            'ownership' => 'Status',
            'accreditation' => 'Akreditasi',
            'address' => 'Alamat',
            'phone' => 'Telepon',
            'email' => 'Email',
            'headmaster' => 'Nama kepala sekolah',
            'headmaster_nip' => 'NIP',
        ];
    }
}
