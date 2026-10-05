<?php

namespace Modules\Attendance\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Modules\Attendance\App\Domain\Queries\TeacherLessons;
use Modules\Attendance\App\Domain\Support\SchoolClock;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;

/**
 * Kelas Saya › Jadwal Hari Ini: today's lessons of the signed-in teacher
 * as a todo list, with the banner that follows the teaching range.
 */
final class TodayController
{
    use KnowsSignedInUser;

    public function __invoke(TeacherLessons $lessons, SchoolClock $clock): Response
    {
        $userId = $this->signedInUserId();
        $today = $clock->today();
        $list = $userId === null ? [] : $lessons->on($userId, $today);

        return Inertia::render('Attendance/Today', [
            'date' => ['iso' => $today, 'label' => $clock->dateLabel($today)],
            'banner' => $lessons->rangeState($list),
            'lessons' => $list,
        ]);
    }
}
