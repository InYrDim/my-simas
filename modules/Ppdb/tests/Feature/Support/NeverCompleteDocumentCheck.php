<?php

namespace Modules\Ppdb\Tests\Feature\Support;

use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\DocumentCheck;

/**
 * A document check that always finds the documents incomplete, to prove
 * verifying stops when they are.
 */
final class NeverCompleteDocumentCheck implements DocumentCheck
{
    public function complete(Applicant $applicant): bool
    {
        return false;
    }
}
