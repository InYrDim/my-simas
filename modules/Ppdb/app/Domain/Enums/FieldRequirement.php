<?php

namespace Modules\Ppdb\App\Domain\Enums;

/**
 * What a period's form does with one field: it must be filled, may be left
 * empty, or is not on the form at all.
 */
enum FieldRequirement: string
{
    case Required = 'required';
    case Optional = 'optional';
    case Off = 'off';

    public function label(): string
    {
        return match ($this) {
            self::Required => 'Wajib',
            self::Optional => 'Opsional',
            self::Off => 'Tidak dipakai',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
