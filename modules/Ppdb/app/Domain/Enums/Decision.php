<?php

namespace Modules\Ppdb\App\Domain\Enums;

/**
 * The selection outcome for an applicant.
 */
enum Decision: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Waitlist = 'waitlist';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum diputuskan',
            self::Accepted => 'Diterima',
            self::Waitlist => 'Cadangan',
            self::Rejected => 'Tidak diterima',
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
