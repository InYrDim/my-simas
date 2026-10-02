<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\NewStudent;
use Modules\Core\App\Contracts\Exceptions\StudentAdmissionRefusedException;

/**
 * Lets a module admit a new student into the current school's records
 * (PPDB: an accepted applicant who has registered again).
 *
 * The student starts active, without a class and without a login account:
 * placing the student in a class is Akademik › Penempatan Siswa, and the
 * account is made through the school's accounts page. The caller checks
 * who may admit; this contract does not.
 */
interface StudentAdmission
{
    /**
     * @return int the id of the new student
     *
     * @throws StudentAdmissionRefusedException the NIS or NISN is already used in this school
     */
    public function admit(NewStudent $student): int;
}
