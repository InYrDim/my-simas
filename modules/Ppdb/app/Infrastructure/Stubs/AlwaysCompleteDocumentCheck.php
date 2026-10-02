<?php

namespace Modules\Ppdb\App\Infrastructure\Stubs;

use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\DocumentCheck;

/**
 * Stand-in until applicants can upload documents: every applicant's
 * documents count as complete.
 */
final class AlwaysCompleteDocumentCheck implements DocumentCheck
{
    public function complete(Applicant $applicant): bool
    {
        return true;
    }
}
