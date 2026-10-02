<?php

namespace Modules\Attendance\App\Domain\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Sick = 'sick';
    case Permit = 'permit';
    case Absent = 'absent';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Hadir',
            self::Late => 'Terlambat',
            self::Sick => 'Sakit',
            self::Permit => 'Izin',
            self::Absent => 'Alpa',
        };
    }

    /**
     * The student was at school: on time or late.
     */
    public function countsAsPresent(): bool
    {
        return $this === self::Present || $this === self::Late;
    }

    /**
     * The statuses a lesson can record: being late is a matter of the
     * school gate, not of a lesson.
     *
     * @return list<self>
     */
    public static function forLessons(): array
    {
        return [self::Present, self::Sick, self::Permit, self::Absent];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
