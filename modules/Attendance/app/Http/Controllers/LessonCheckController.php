<?php

namespace Modules\Attendance\App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Modules\Attendance\App\Domain\Actions\ToggleLessonCheck;
use Modules\Attendance\App\Http\Concerns\KnowsSignedInUser;
use Modules\Attendance\App\Http\Requests\LessonCheckRequest;

/**
 * The todo checkbox of Jadwal Hari Ini: tick a lesson off once its hour
 * is over, or take the tick back.
 */
final class LessonCheckController
{
    use KnowsSignedInUser;

    public function update(LessonCheckRequest $request, ToggleLessonCheck $toggle): RedirectResponse
    {
        $userId = $this->signedInUserId();

        if ($userId !== null) {
            $toggle->handle(
                $userId,
                (int) $request->validated('period_slot_id'),
                (bool) $request->validated('checked'),
            );
        }

        return back();
    }
}
