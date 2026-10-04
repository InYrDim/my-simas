<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\App\Domain\Queries\SignedInPerson;
use Modules\Core\App\Domain\Queries\TeacherClasses;
use Modules\Core\App\Domain\Queries\TeacherTimetable;
use Modules\Identity\App\Contracts\ResolvesUsers;
use Modules\Platform\App\Contracts\TenantContext;

/**
 * "Saya" › Profil: the signed-in person's own record and sign-in, read
 * only. Behind `core.me.view`; nothing here takes an id, so it can only
 * ever show the signed-in account's own data.
 */
final class MeController
{
    public function profile(TenantContext $context, ResolvesUsers $users, SignedInPerson $person): Response
    {
        $tenant = $context->currentOrFail();
        $userId = (int) Auth::id();
        $account = $users->findMany([$userId])[$userId] ?? null;

        /** @var array<string, array{label: string}> $definitions */
        $definitions = config('roles', []);

        return Inertia::render('Core/Me/Profile', [
            'school' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'person' => $person->forUser($userId),
            'account' => $account === null ? null : [
                'name' => $account->name,
                'login' => $account->username ?? $account->email,
                'roles' => array_map(
                    fn (string $name): string => $definitions[$name]['label'] ?? $name,
                    array_values($account->roles),
                ),
            ],
        ]);
    }

    /**
     * "Saya" › Kelas Saya: the classes of the active year this teacher is
     * homeroom teacher of or teaches in. Behind `core.teaching.view`.
     */
    public function classes(TenantContext $context, TeacherClasses $classes): Response
    {
        $tenant = $context->currentOrFail();

        return Inertia::render('Core/Me/Classes', [
            'school' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'classes' => $classes->forUser(Auth::id()),
        ]);
    }

    /**
     * "Saya" › Jadwal Mengajar: the teacher own weekly lessons, by weekday.
     * Behind `core.teaching.view`.
     */
    public function timetable(TenantContext $context, TeacherTimetable $timetable): Response
    {
        $tenant = $context->currentOrFail();

        return Inertia::render('Core/Me/Timetable', [
            'school' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'days' => $timetable->forUser(Auth::id()),
        ]);
    }
}
