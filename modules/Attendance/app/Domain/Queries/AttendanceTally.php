<?php

namespace Modules\Attendance\App\Domain\Queries;

use Modules\Attendance\App\Domain\Enums\AttendanceStatus;

/**
 * Counts of daily records per status, and the share that was at school.
 */
final class AttendanceTally
{
    /**
     * @var array<string, int> keyed by status value
     */
    private array $counts;

    public function __construct()
    {
        $this->counts = array_fill_keys(AttendanceStatus::values(), 0);
    }

    public function add(AttendanceStatus $status, int $times = 1): void
    {
        $this->counts[$status->value] += $times;
    }

    public function merge(self $other): void
    {
        foreach ($other->counts as $status => $count) {
            $this->counts[$status] += $count;
        }
    }

    public function of(AttendanceStatus $status): int
    {
        return $this->counts[$status->value];
    }

    /**
     * Every record counted, whatever its status.
     */
    public function total(): int
    {
        return array_sum($this->counts);
    }

    /**
     * Records of a student at school: on time or late.
     */
    public function attended(): int
    {
        return $this->of(AttendanceStatus::Present) + $this->of(AttendanceStatus::Late);
    }

    /**
     * Share at school in whole percent; null when nothing was recorded.
     */
    public function percent(): ?int
    {
        return $this->total() === 0 ? null : (int) round($this->attended() / $this->total() * 100);
    }

    /**
     * @return array{present: int, late: int, sick: int, permit: int, absent: int}
     */
    public function toArray(): array
    {
        return [
            'present' => $this->of(AttendanceStatus::Present),
            'late' => $this->of(AttendanceStatus::Late),
            'sick' => $this->of(AttendanceStatus::Sick),
            'permit' => $this->of(AttendanceStatus::Permit),
            'absent' => $this->of(AttendanceStatus::Absent),
        ];
    }
}
