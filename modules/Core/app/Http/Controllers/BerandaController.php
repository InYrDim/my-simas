<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantRoles;

/**
 * The school's landing record: who this school is, what day it is on
 * the school's own clock, and the one thing still waiting there.
 *
 * Reads only public surfaces — Platform's tenant context and role names,
 * Identity's account counts. No Eloquent model crosses the boundary.
 */
final class BerandaController
{
    public function __invoke(
        TenantContext $context,
        ResolvesUsers $users,
        TenantRoles $roles,
    ): Response {
        $tenant = $context->currentOrFail();

        // The school's own clock, not the server's and not the phone's.
        $today = now($tenant->timezone);
        $summary = $users->currentTenantSummary();

        return Inertia::render('Core/Beranda', [
            'school' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'timezone' => $tenant->timezone,
            ],
            'today' => [
                'label' => $today->locale('id')->isoFormat('dddd, D MMMM Y'),
                'iso' => $today->toDateString(),
            ],
            'accounts' => [
                'total' => $summary->total,
                'active' => $summary->active,
                'awaitingActivation' => $summary->awaitingActivation,
                'deactivated' => $summary->deactivated,
                'withoutRole' => $summary->withoutRole,
            ],
            'roles' => $this->roleLabels($roles->names($tenant->id)),
            'can' => [
                'viewUsers' => Gate::allows('identity.users.view'),
                'invite' => Gate::allows('identity.users.create'),
            ],
        ]);
    }

    /**
     * Role labels for display, in the tenant's own set. Machine names
     * never reach the UI; labels are config-only display data
     * (modules/Identity/config/roles.php), an unknown name falls back
     * to itself rather than vanishing.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function roleLabels(array $names): array
    {
        /** @var array<string, array{label: string}> $definitions */
        $definitions = config('roles', []);

        return array_values(array_map(
            fn (string $name): string => $definitions[$name]['label'] ?? $name,
            $names,
        ));
    }
}
