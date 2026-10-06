<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Models\AttendanceSetting;

final class SaveAttendanceSettings
{
    /**
     * @param  string  $lateAfter  H:i, the last minute a student is still on time
     * @param  int  $lessonScanEarlyMinutes  how long before a lesson starts it may be scanned
     * @param  string  $gateOpensAt  H:i, the first minute the gate takes scans
     * @param  string  $gateClosesAt  H:i, the last minute the gate takes scans
     */
    public function handle(string $lateAfter, bool $gateEnabled, bool $lessonEnabled, int $lessonScanEarlyMinutes, string $gateOpensAt, string $gateClosesAt): AttendanceSetting
    {
        $settings = AttendanceSetting::current();
        $settings->fill([
            'late_after' => "{$lateAfter}:00",
            'gate_enabled' => $gateEnabled,
            'lesson_enabled' => $lessonEnabled,
            'lesson_scan_early_minutes' => $lessonScanEarlyMinutes,
            'gate_opens_at' => "{$gateOpensAt}:00",
            'gate_closes_at' => "{$gateClosesAt}:00",
        ])->save();

        return $settings;
    }
}
