<?php

namespace Modules\Attendance\App\Infrastructure\Mock;

/**
 * Static sample data for the Absensi mockup pages. Nothing here is
 * persisted; replaced by repositories in the DB phase.
 */
final class AttendanceMockData
{
    /**
     * @return array{iso: string, label: string}
     */
    public function date(): array
    {
        return ['iso' => '2026-10-01', 'label' => 'Kamis, 1 Oktober 2026'];
    }

    /**
     * @return array{present: int, sick: int, permit: int, absent: int, pending: int, total: int}
     */
    public function totals(): array
    {
        return ['present' => 561, 'sick' => 14, 'permit' => 9, 'absent' => 7, 'pending' => 51, 'total' => 642];
    }

    /**
     * @return list<array{id: int, name: string, homeroom: string, present: int, sick: int, permit: int, absent: int, total: int, submitted: bool}>
     */
    public function classes(): array
    {
        return [
            ['id' => 1, 'name' => 'X IPA 1', 'homeroom' => 'Budi Santoso, S.Pd.', 'present' => 34, 'sick' => 1, 'permit' => 0, 'absent' => 1, 'total' => 36, 'submitted' => true],
            ['id' => 2, 'name' => 'X IPA 2', 'homeroom' => 'Rina Wulandari, S.Si.', 'present' => 33, 'sick' => 2, 'permit' => 1, 'absent' => 0, 'total' => 36, 'submitted' => true],
            ['id' => 3, 'name' => 'X IPS 1', 'homeroom' => 'Ahmad Fauzi, M.Pd.', 'present' => 31, 'sick' => 1, 'permit' => 2, 'absent' => 2, 'total' => 36, 'submitted' => true],
            ['id' => 4, 'name' => 'XI IPA 1', 'homeroom' => 'Dewi Lestari, S.Pd.', 'present' => 0, 'sick' => 0, 'permit' => 0, 'absent' => 0, 'total' => 35, 'submitted' => false],
            ['id' => 5, 'name' => 'XI IPS 1', 'homeroom' => 'Hendra Gunawan, S.Pd.', 'present' => 32, 'sick' => 2, 'permit' => 0, 'absent' => 1, 'total' => 35, 'submitted' => true],
            ['id' => 6, 'name' => 'XII IPA 1', 'homeroom' => 'Siti Nurhaliza, M.Pd.', 'present' => 0, 'sick' => 0, 'permit' => 0, 'absent' => 0, 'total' => 34, 'submitted' => false],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function classOptions(): array
    {
        return array_map(
            fn (array $class): array => ['value' => (string) $class['id'], 'label' => $class['name']],
            $this->classes(),
        );
    }

    /**
     * @return list<array{id: int, name: string, nis: string, status: string}>
     */
    public function students(): array
    {
        $names = [
            'Aditya Pratama', 'Alya Rahmawati', 'Bagas Saputra', 'Citra Maharani', 'Dimas Aryo',
            'Eka Putri Lestari', 'Farhan Maulana', 'Gita Permata', 'Hana Safitri', 'Ilham Ramadhan',
            'Jihan Aulia', 'Kevin Anggara',
        ];
        $statuses = ['present', 'present', 'sick', 'present', 'present', 'present', 'absent', 'present', 'permit', 'present', 'present', 'present'];

        return array_map(fn (string $name, string $status, int $index): array => [
            'id' => $index + 1,
            'name' => $name,
            'nis' => (string) (24001 + $index),
            'status' => $status,
        ], $names, $statuses, array_keys($names));
    }

    /**
     * @return array{iso: string, label: string}
     */
    public function month(): array
    {
        return ['iso' => '2026-09', 'label' => 'September 2026'];
    }

    /**
     * September 2026, 22 school days.
     *
     * @return list<array{id: int, name: string, nis: string, present: int, sick: int, permit: int, absent: int, percent: int}>
     */
    public function monthlyRows(): array
    {
        $rows = [
            ['Aditya Pratama', 22, 0, 0, 0],
            ['Alya Rahmawati', 21, 1, 0, 0],
            ['Bagas Saputra', 18, 3, 1, 0],
            ['Citra Maharani', 22, 0, 0, 0],
            ['Dimas Aryo', 20, 0, 1, 1],
            ['Farhan Maulana', 15, 2, 1, 4],
            ['Hana Safitri', 21, 0, 1, 0],
            ['Ilham Ramadhan', 19, 1, 2, 0],
        ];

        return array_map(fn (array $row, int $index): array => [
            'id' => $index + 1,
            'name' => $row[0],
            'nis' => (string) (24001 + $index),
            'present' => $row[1],
            'sick' => $row[2],
            'permit' => $row[3],
            'absent' => $row[4],
            'percent' => (int) round($row[1] / 22 * 100),
        ], $rows, array_keys($rows));
    }
}
