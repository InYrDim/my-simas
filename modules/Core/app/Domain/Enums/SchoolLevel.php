<?php

namespace Modules\Core\App\Domain\Enums;

/**
 * School level (jenjang). Drives the grade range, whether majors exist
 * and the label of the homeroom teacher.
 */
enum SchoolLevel: string
{
    case Sd = 'sd';
    case Smp = 'smp';
    case Sma = 'sma';
    case Smk = 'smk';

    public function label(): string
    {
        return match ($this) {
            self::Sd => 'SD/MI',
            self::Smp => 'SMP/MTs',
            self::Sma => 'SMA/MA',
            self::Smk => 'SMK',
        };
    }

    public function hasMajors(): bool
    {
        return $this === self::Sma || $this === self::Smk;
    }

    public function homeroomLabel(): string
    {
        return $this === self::Sd ? 'Guru Kelas' : 'Wali Kelas';
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $level): array => ['value' => $level->value, 'label' => $level->label()],
            self::cases(),
        );
    }
}
