<?php

namespace Modules\Ppdb\Tests\Feature\Support;

use Modules\Ppdb\App\Domain\Models\Applicant;
use Modules\Ppdb\App\Domain\Support\ResultAnnouncer;

/**
 * Remembers whom the school told about the results, to prove who is told
 * and how often.
 */
final class RecordingResultAnnouncer implements ResultAnnouncer
{
    /**
     * Registration numbers, in the order they were announced.
     *
     * @var list<string>
     */
    public array $announced = [];

    public function announce(Applicant $applicant): bool
    {
        $this->announced[] = $applicant->number;

        return true;
    }
}
