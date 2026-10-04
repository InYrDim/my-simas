<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\App\Domain\Models\Student;
use Modules\Core\App\Domain\Models\Teacher;
use Modules\Core\App\Domain\Queries\SetupChecklist;
use Modules\Core\App\Domain\Queries\TeacherClasses;
use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Platform\App\Contracts\TenantContext;
use Modules\Platform\App\Contracts\TenantNavigation;
use Modules\Platform\App\Contracts\TenantRoles;

/**
 * The school's landing record: who this school is, what day it is on
 * the school's own clock, and the one thing still waiting there.
 *
 * Reads only public surfaces — Platform's tenant context and role names,
 * Identity's account counts. No Eloquent model crosses the boundary.
 *
 * `me` is the student or the teacher whose record carries the signed-in
 * account. The account figures and the role list are the business of
 * whoever manages users; everyone else gets `accounts: null`.
 *
 * `shortcuts` and `classes` are for everyone but the account managers: the
 * quick links their role may use and, for a teacher, the classes they look
 * after in the active year.
 *
 * `setup` is the school's setup checklist, for whoever manages master data
 * and only while a required step is still open; null for everyone else
 * and once the school is set up.
 */
final class BerandaController
{
    public function __invoke(
        TenantContext $context,
        ResolvesUsers $users,
        TenantRoles $roles,
        SetupChecklist $setup,
        TenantNavigation $navigation,
        TeacherClasses $teacherClasses,
    ): Response {
        $tenant = $context->currentOrFail();

        // The school's own clock, not the server's and not the phone's.
        $today = now($tenant->timezone);
        $canViewUsers = Gate::allows('identity.users.view');
        $summary = $canViewUsers ? $users->currentTenantSummary() : null;

        return Inertia::render('Core/Beranda', [
            'school' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'timezone' => $tenant->timezone,
            ],
            'today' => [
                'label' => $today->settings(['locale' => 'id'])->isoFormat('dddd, D MMMM Y'),
                'iso' => $today->toDateString(),
            ],
            'me' => $this->me(),
            'accounts' => $summary === null ? null : [
                'total' => $summary->total,
                'active' => $summary->active,
                'awaitingActivation' => $summary->awaitingActivation,
                'deactivated' => $summary->deactivated,
                'withoutRole' => $summary->withoutRole,
            ],
            'roles' => $canViewUsers ? $this->roleLabels(array_values($roles->names($tenant->id))) : [],
            'can' => [
                'viewUsers' => $canViewUsers,
                'invite' => Gate::allows('identity.users.create'),
            ],
            'setup' => Gate::allows('core.master.manage') ? $setup->forCurrentSchool() : null,
            'shortcuts' => $summary === null ? $this->shortcuts($navigation) : [],
            'classes' => $summary === null ? $teacherClasses->forUser(Auth::id()) : [],
        ]);
    }

    /**
     * The quick links the signed-in user may use, taken from the sidebar
     * entries the modules marked as shortcuts (already filtered by module
     * and permission). Core names no other module here.
     *
     * @return list<array{label: string, href: string, icon: string}>
     */
    private function shortcuts(TenantNavigation $navigation): array
    {
        $links = [];

        foreach ($navigation->forCurrentUser() as $item) {
            if ($item['shortcut']) {
                $links[] = ['label' => $item['label'], 'href' => $item['href'], 'icon' => $item['icon']];
            }

            foreach ($item['children'] as $child) {
                if ($child['shortcut']) {
                    $links[] = ['label' => $child['label'], 'href' => $child['href'], 'icon' => $item['icon']];
                }
            }
        }

        return $links;
    }

    /**
     * The student or teacher record linked to the signed-in account.
     *
     * @return array{kind: 'student', name: string, nis: string, class: string|null}|array{kind: 'teacher', name: string, duty: string}|null
     */
    private function me(): ?array
    {
        $userId = Auth::id();

        if ($userId === null) {
            return null;
        }

        $student = Student::query()->with('classGroup')->where('user_id', $userId)->first();

        if ($student !== null) {
            return [
                'kind' => 'student',
                'name' => $student->name,
                'nis' => $student->nis,
                'class' => $student->classGroup?->name,
            ];
        }

        $teacher = Teacher::query()->where('user_id', $userId)->first();

        return $teacher === null ? null : [
            'kind' => 'teacher',
            'name' => $teacher->name,
            'duty' => $teacher->duty,
        ];
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

        return array_map(
            fn (string $name): string => $definitions[$name]['label'] ?? $name,
            $names,
        );
    }
}
