<?php

namespace Modules\Ppdb\Tests\Feature\Support;

use Modules\Core\App\Contracts\DTOs\NewStudent;
use Modules\Core\App\Contracts\StudentAdmission;

/**
 * Remembers what Ppdb handed to Core, to prove the applicant's data is
 * passed on as it is. It admits nobody: the id it returns is made up.
 */
final class RecordingStudentAdmission implements StudentAdmission
{
    /**
     * @var list<NewStudent>
     */
    public array $admitted = [];

    public function admit(NewStudent $student): int
    {
        $this->admitted[] = $student;

        return 9000 + count($this->admitted);
    }
}
