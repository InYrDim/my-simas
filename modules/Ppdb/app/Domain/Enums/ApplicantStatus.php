<?php

namespace Modules\Ppdb\App\Domain\Enums;

/**
 * Where an applicant stands with the committee's check of the data and
 * documents. The selection outcome is a separate matter (Decision).
 */
enum ApplicantStatus: string
{
    case Submitted = 'submitted';
    case Revision = 'revision';
    case Verified = 'verified';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Menunggu verifikasi',
            self::Revision => 'Perlu perbaikan',
            self::Verified => 'Terverifikasi',
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
