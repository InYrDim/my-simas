<?php

namespace Modules\Ppdb\App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * The rules and names of an applicant's own fields, shared by the
 * committee's form and the applicant's own form (which has no wave: the
 * wave open today is the applicant's).
 */
final class ApplicantFieldRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'path_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'nisn' => ['nullable', 'string', 'max:32'],
            'origin_school' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_phone' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'wave_id' => 'Gelombang',
            'path_id' => 'Jalur',
            'name' => 'Nama lengkap',
            'gender' => 'Jenis kelamin',
            'birth_place' => 'Tempat lahir',
            'birth_date' => 'Tanggal lahir',
            'nisn' => 'NISN',
            'origin_school' => 'Asal sekolah',
            'address' => 'Alamat',
            'guardian_name' => 'Nama wali',
            'guardian_phone' => 'Telepon wali',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.'];
    }
}
