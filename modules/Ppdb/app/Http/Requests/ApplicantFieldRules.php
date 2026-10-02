<?php

namespace Modules\Ppdb\App\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Ppdb\App\Domain\Enums\FieldRequirement;
use Modules\Ppdb\App\Domain\Support\FormFields;

/**
 * The rules and names of an applicant's own fields, shared by the
 * committee's form and the applicant's own form (which has no wave: the
 * wave open today is the applicant's). Which of the adjustable fields are
 * asked, and whether they are required, is the period's choice.
 */
final class ApplicantFieldRules
{
    /**
     * @param  array<string, FieldRequirement>|null  $fields  the period's form; the usual one when there is no period
     * @return array<string, mixed>
     */
    public static function rules(?array $fields = null): array
    {
        return [
            'path_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            ...FormFields::rules($fields ?? FormFields::defaults()),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        $names = [
            'wave_id' => 'Gelombang',
            'path_id' => 'Jalur',
            'name' => 'Nama lengkap',
            'gender' => 'Jenis kelamin',
        ];

        foreach (FormFields::keys() as $key) {
            $names[$key] = FormFields::label($key);
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return ['birth_date.before_or_equal' => 'Tanggal lahir tidak boleh di masa depan.'];
    }
}
