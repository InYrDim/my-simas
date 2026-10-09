<?php

namespace Modules\Attendance\App\Domain\Actions;

use Modules\Attendance\App\Domain\Models\AttendanceSetting;

final class SaveAttendanceSettings
{
    /**
     * @param  string  $lateAfter  H:i, the last minute a student is still on time
     * @param  int  $lessonScanEarlyMinutes  how long before a lesson starts it may be scanned
     * @param  bool  $lessonCopyPreviousEnabled  whether a teacher may copy the previous lesson's roll
     * @param  string  $gateOpensAt  H:i, the first minute the gate takes scans
     * @param  string  $gateClosesAt  H:i, the last minute the gate takes scans
     * @param  bool  $staticQrEnabled  whether the printed static QR is accepted and may be printed
     */
    public function handle(string $lateAfter, bool $gateEnabled, bool $lessonEnabled, int $lessonScanEarlyMinutes, bool $lessonCopyPreviousEnabled, string $gateOpensAt, string $gateClosesAt, bool $staticQrEnabled): AttendanceSetting
    {
        $settings = AttendanceSetting::current();
        $settings->fill([
            'late_after' => "{$lateAfter}:00",
            'gate_enabled' => $gateEnabled,
            'lesson_enabled' => $lessonEnabled,
            'lesson_scan_early_minutes' => $lessonScanEarlyMinutes,
            'lesson_copy_previous_enabled' => $lessonCopyPreviousEnabled,
            'static_qr_enabled' => $staticQrEnabled,
            'gate_opens_at' => "{$gateOpensAt}:00",
            'gate_closes_at' => "{$gateClosesAt}:00",
        ])->save();

        return $settings;
    }
}
