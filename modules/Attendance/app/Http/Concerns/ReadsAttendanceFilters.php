<?php

namespace Modules\Attendance\App\Http\Concerns;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Modules\Attendance\App\Domain\Support\SchoolClock;

/**
 * Reads the day and the month a page was asked for from the query string.
 * Anything that is not a real day up to today falls back to today.
 */
trait ReadsAttendanceFilters
{
    /**
     * `?tanggal=Y-m-d`, never in the future.
     */
    protected function requestedDate(Request $request, SchoolClock $clock): string
    {
        $date = (string) $request->query('tanggal', '');
        $today = $clock->today();

        if (! $this->isDate($date, 'Y-m-d') || $date > $today) {
            return $today;
        }

        return $date;
    }

    /**
     * `?bulan=Y-m`, never in the future.
     */
    protected function requestedMonth(Request $request, SchoolClock $clock): string
    {
        $month = (string) $request->query('bulan', '');
        $current = substr($clock->today(), 0, 7);

        if (! $this->isDate($month, 'Y-m') || $month > $current) {
            return $current;
        }

        return $month;
    }

    /**
     * `?kelas=<id>`, as the string the class options carry.
     */
    protected function requestedClass(Request $request): ?string
    {
        $class = $request->query('kelas');

        return is_string($class) ? $class : null;
    }

    /**
     * @return array{iso: string, label: string, isToday: bool}
     */
    protected function dateProp(string $date, SchoolClock $clock): array
    {
        return ['iso' => $date, 'label' => $clock->dateLabel($date), 'isToday' => $date === $clock->today()];
    }

    private function isDate(string $value, string $format): bool
    {
        $parsed = DateTimeImmutable::createFromFormat("!{$format}", $value);

        return $parsed !== false && $parsed->format($format) === $value;
    }
}
