<?php

namespace Modules\Core\App\Contracts;

use Modules\Core\App\Contracts\DTOs\DashboardWidget;

/**
 * A module's contribution to the Beranda: the blocks it can show about
 * its own data. Registered with DashboardRegistry.
 *
 * Implementations are resolved from the container and run inside the
 * tenant context of the request, for the signed-in user, only while their
 * module is active for the tenant. A provider returns every widget it
 * could show and names the permission each needs; it may return fewer
 * when the signed-in person has nothing of its kind (a student's widgets
 * for a teacher, say).
 */
interface DashboardWidgetProvider
{
    /**
     * @return list<DashboardWidget>
     */
    public function widgets(): array;
}
