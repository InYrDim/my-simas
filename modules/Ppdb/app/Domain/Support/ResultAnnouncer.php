<?php

namespace Modules\Ppdb\App\Domain\Support;

use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Tells an applicant what was decided about them when the school announces
 * its results (a WhatsApp message to the guardian, later). Until that
 * feature exists the implementation bound in the provider does nothing and
 * says it worked; the result is on the applicant's own page either way.
 */
interface ResultAnnouncer
{
    public function announce(Applicant $applicant): bool;
}
