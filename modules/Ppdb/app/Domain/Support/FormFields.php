<?php

namespace Modules\Ppdb\App\Domain\Support;

use Modules\Ppdb\App\Domain\Enums\FieldRequirement;

/**
 * The fields of the registration form a school may switch on or off, with
 * the usual state of each and the rules that go with it. The path, the name
 * and the gender are not here: selection and re-registration need them, so
 * every form asks for them.
 */
final class FormFields
{
    /**
     * The adjustable fields: label, usual state and the rules on the value.
     *
     * @var array<string, array{label: string, default: FieldRequirement, rules: list<string>}>
     */
    private const FIELDS = [
        'birth_place' => ['label' => 'Tempat lahir', 'default' => FieldRequirement::Optional, 'rules' => ['string', 'max:100']],
        'birth_date' => ['label' => 'Tanggal lahir', 'default' => FieldRequirement::Required, 'rules' => ['date_format:Y-m-d', 'before_or_equal:today']],
        'nisn' => ['label' => 'NISN', 'default' => FieldRequirement::Optional, 'rules' => ['string', 'max:32']],
        'origin_school' => ['label' => 'Asal sekolah', 'default' => FieldRequirement::Required, 'rules' => ['string', 'max:255']],
        'address' => ['label' => 'Alamat', 'default' => FieldRequirement::Optional, 'rules' => ['string', 'max:500']],
        'guardian_name' => ['label' => 'Nama wali', 'default' => FieldRequirement::Required, 'rules' => ['string', 'max:255']],
        'guardian_phone' => ['label' => 'Telepon wali', 'default' => FieldRequirement::Required, 'rules' => ['string', 'max:32']],
    ];

    /**
     * Fields every form has, shown on the settings page as fixed.
     *
     * @var list<string>
     */
    public const FIXED_LABELS = ['Jalur', 'Nama lengkap', 'Jenis kelamin'];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::FIELDS);
    }

    public static function label(string $key): string
    {
        return self::FIELDS[$key]['label'] ?? $key;
    }

    /**
     * The usual form.
     *
     * @return array<string, FieldRequirement>
     */
    public static function defaults(): array
    {
        return array_map(fn (array $field): FieldRequirement => $field['default'], self::FIELDS);
    }

    /**
     * What a period stored, over the usual form: a field or a value it does
     * not know is ignored.
     *
     * @param  array<array-key, mixed>|null  $stored
     * @return array<string, FieldRequirement>
     */
    public static function resolve(?array $stored): array
    {
        $fields = self::defaults();

        foreach ($stored ?? [] as $key => $value) {
            $requirement = is_string($value) ? FieldRequirement::tryFrom($value) : null;

            if (is_string($key) && array_key_exists($key, $fields) && $requirement !== null) {
                $fields[$key] = $requirement;
            }
        }

        return $fields;
    }

    /**
     * The validation rules of an applicant's fields under a form: a field
     * that is off has none, so it never reaches the validated data.
     *
     * @param  array<string, FieldRequirement>  $fields
     * @return array<string, list<string>>
     */
    public static function rules(array $fields): array
    {
        $rules = [];

        foreach (self::FIELDS as $key => $field) {
            $requirement = $fields[$key] ?? $field['default'];

            if ($requirement === FieldRequirement::Off) {
                continue;
            }

            $rules[$key] = [$requirement === FieldRequirement::Required ? 'required' : 'nullable', ...$field['rules']];
        }

        return $rules;
    }

    /**
     * The form as a page needs it: the state of each field by key.
     *
     * @param  array<string, FieldRequirement>  $fields
     * @return array<string, string>
     */
    public static function values(array $fields): array
    {
        return array_map(fn (FieldRequirement $requirement): string => $requirement->value, $fields);
    }
}
