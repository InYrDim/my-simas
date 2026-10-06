<?php

namespace Modules\Attendance\App\Domain\Support;

use Modules\Attendance\App\Domain\Exceptions\AttendanceException;
use Modules\Attendance\App\Domain\Models\AttendanceSetting;
use Modules\Core\App\Contracts\DTOs\BellSlot;

/**
 * The times a scan is accepted, on the school's clock. A lesson is open
 * from a few minutes before it starts (the school's setting) until it
 * ends; corrections outside that go through Riwayat Absensi.
 */
final class ScanWindow
{
    public function __construct(
        private readonly SchoolClock $clock,
    ) {}

    /**
     * @throws AttendanceException
     */
    public function assertLessonOpen(BellSlot $slot): void
    {
        $early = AttendanceSetting::current()->lesson_scan_early_minutes;
        $now = $this->minutes($this->clock->now()->format('H:i'));
        $range = str_replace(':', '.', $slot->startsAt).'–'.str_replace(':', '.', $slot->endsAt);

        if ($now < $this->minutes($slot->startsAt) - $early) {
            throw new AttendanceException("Jam pelajaran {$range} belum dibuka untuk dipindai. Pemindaian dibuka {$early} menit sebelum jam mulai.");
        }

        if ($now >= $this->minutes($slot->endsAt)) {
            throw new AttendanceException("Jam pelajaran {$range} sudah selesai. Koreksi lewat Riwayat Absensi.");
        }
    }

    /**
     * Minutes since midnight of an `H:i` time.
     */
    private function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
