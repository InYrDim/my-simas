<?php

namespace Modules\Ppdb\App\Domain\Enums;

enum PeriodStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Konsep',
            self::Active => 'Berjalan',
            self::Closed => 'Ditutup',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
