<?php

namespace Modules\Ppdb\App\Domain\Support;

/**
 * The ten fields of the applicant record, which every period's form starts
 * with, in their usual order, with the usual state of each and the rules on
 * the value. The path, name and gender are locked: selection and
 * re-registration need them, so they are always asked and always required.
 * The rest a school may make optional or archive.
 */
final class BuiltinFields
{
    /**
     * @var array<string, array{label: string, required: bool, locked: bool, rules: list<string>}>
     */
    private const FIELDS = [
        'path_id' => ['label' => 'Jalur', 'required' => true, 'locked' => true, 'rules' => ['integer']],
        'name' => ['label' => 'Nama lengkap', 'required' => true, 'locked' => true, 'rules' => ['string', 'max:255']],
        'gender' => ['label' => 'Jenis kelamin', 'required' => true, 'locked' => true, 'rules' => ['in:L,P']],
        'nisn' => ['label' => 'NISN', 'required' => false, 'locked' => false, 'rules' => ['string', 'max:32']],
        'birth_place' => ['label' => 'Tempat lahir', 'required' => false, 'locked' => false, 'rules' => ['string', 'max:100']],
        'birth_date' => ['label' => 'Tanggal lahir', 'required' => true, 'locked' => false, 'rules' => ['date_format:Y-m-d', 'before_or_equal:today']],
        'origin_school' => ['label' => 'Asal sekolah', 'required' => true, 'locked' => false, 'rules' => ['string', 'max:255']],
        'address' => ['label' => 'Alamat', 'required' => false, 'locked' => false, 'rules' => ['string', 'max:500']],
        'guardian_name' => ['label' => 'Nama wali', 'required' => true, 'locked' => false, 'rules' => ['string', 'max:255']],
        'guardian_phone' => ['label' => 'Telepon wali', 'required' => true, 'locked' => false, 'rules' => ['string', 'max:32']],
    ];

    /**
     * @return list<string> the keys in their usual order
     */
    public static function keys(): array
    {
        return array_keys(self::FIELDS);
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::FIELDS);
    }

    public static function label(string $key): string
    {
        return self::FIELDS[$key]['label'] ?? $key;
    }

    public static function isLocked(string $key): bool
    {
        return self::FIELDS[$key]['locked'] ?? false;
    }

    public static function isRequiredByDefault(string $key): bool
    {
        return self::FIELDS[$key]['required'] ?? false;
    }

    /**
     * The rules on the value of a field, apart from whether it is required.
     *
     * @return list<string>
     */
    public static function valueRules(string $key): array
    {
        return self::FIELDS[$key]['rules'] ?? [];
    }
}
