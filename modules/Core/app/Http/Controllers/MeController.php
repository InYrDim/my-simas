<?php

namespace Modules\Core\App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Core\App\Contracts\DTOs\ScheduleDay;
use Modules\Core\App\Contracts\DTOs\ScheduleLesson;
use Modules\Core\App\Contracts\TeacherSchedule;
use Modules\Core\App\Domain\Queries\SignedInPerson;
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
     * "Saya" › Jadwal Mengajar: the teacher own weekly lessons, by weekday.
     * Behind `core.teaching.view`.
     */
    public function timetable(TenantContext $context, TeacherSchedule $schedule): Response
    {
        $tenant = $context->currentOrFail();
        $userId = Auth::id();

        return Inertia::render('Core/Me/Timetable', [
            'school' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'days' => $userId === null ? [] : $this->days($schedule->week((int) $userId)),
        ]);
    }

    /**
     * @param  list<ScheduleDay>  $week
     * @return list<array{day: string, dayNumber: int, lessons: list<array{order: int, start: string, end: string, class: string, subject: string}>}>
     */
    private function days(array $week): array
    {
        return array_map(fn (ScheduleDay $day): array => [
            'day' => $day->dayName,
            'dayNumber' => $day->day,
            'lessons' => array_map(fn (ScheduleLesson $lesson): array => [
                'order' => $lesson->order,
                'start' => $lesson->startsAt,
                'end' => $lesson->endsAt,
                'class' => $lesson->className,
                'subject' => $lesson->subjectName,
            ], $day->lessons),
        ], $week);
    }
}
