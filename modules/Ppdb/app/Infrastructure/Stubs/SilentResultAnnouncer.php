<?php

namespace Modules\Ppdb\App\Infrastructure\Stubs;

use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\ResultAnnouncer;

/**
 * Stand-in until results are sent to guardians: nothing is sent and every
 * applicant counts as told.
 */
final class SilentResultAnnouncer implements ResultAnnouncer
{
    public function announce(Applicant $applicant): bool
    {
        return true;
    }
}
