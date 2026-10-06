<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Models\AttendanceSetting;

final class SaveAttendanceSettings
{
    /**
     * @param  string  $lateAfter  H:i, the last minute a student is still on time
     * @param  int  $lessonScanEarlyMinutes  how long before a lesson starts it may be scanned
     */
    public function handle(string $lateAfter, bool $gateEnabled, bool $lessonEnabled, int $lessonScanEarlyMinutes): AttendanceSetting
    {
        $settings = AttendanceSetting::current();
        $settings->fill([
            'late_after' => "{$lateAfter}:00",
            'gate_enabled' => $gateEnabled,
            'lesson_enabled' => $lessonEnabled,
            'lesson_scan_early_minutes' => $lessonScanEarlyMinutes,
        ])->save();

        return $settings;
    }
}
