<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Models\AttendanceSetting;

final class SaveAttendanceSettings
{
    /**
     * @param  string  $lateAfter  H:i, the last minute a student is still on time
     */
    public function handle(string $lateAfter, bool $gateEnabled, bool $lessonEnabled): AttendanceSetting
    {
        $settings = AttendanceSetting::current();
        $settings->fill([
            'late_after' => "{$lateAfter}:00",
            'gate_enabled' => $gateEnabled,
            'lesson_enabled' => $lessonEnabled,
        ])->save();

        return $settings;
    }
}
