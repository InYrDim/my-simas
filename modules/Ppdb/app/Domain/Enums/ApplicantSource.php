<?php

namespace Modules\Ppdb\App\Domain\Enums;

/**
 * How an applicant came to be registered.
 */
enum ApplicantSource: string
{
    /** The applicant registered through their own PPDB account. */
    case Online = 'online';

    /** The committee entered the applicant by hand. */
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::Staff => 'Panitia',
        };
    }
}
