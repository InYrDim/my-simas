<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\StudentAction;

/**
 * A module's contribution to the Siswa list: the actions it offers on the
 * students. Registered with StudentActionRegistry; resolved from the
 * container inside the tenant context of the request, only while its
 * module is active for the tenant. A provider returns nothing when the
 * signed-in person may not use them or the school has them switched off.
 */
interface StudentActionProvider
{
    /**
     * @return list<StudentAction>
     */
    public function actions(): array;
}
