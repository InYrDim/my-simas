<?php

namespace Modules\Ppdb\App\Domain\Support;

use Modules\Ppdb\App\Domain\Models\Applicant;

/**
 * Whether an applicant's documents are complete. Document upload is a
 * later feature; until then the implementation bound in the provider says
 * yes, and verifying is the committee's own judgement.
 */
interface DocumentCheck
{
    public function complete(Applicant $applicant): bool;
}
