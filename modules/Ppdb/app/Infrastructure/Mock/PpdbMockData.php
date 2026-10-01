<?php

namespace Modules\Ppdb\App\Infrastructure\Mock;

/**
 * Static sample data for the PPDB mockup pages. Nothing here is
 * persisted; replaced by repositories in the DB phase.
 */
final class PpdbMockData
{
    /**
     * @return array{name: string, status: string, closesOn: string}
     */
    public function period(): array
    {
        return ['name' => 'PPDB Tahun Ajaran 2027/2028', 'status' => 'open', 'closesOn' => '30 Juni 2027'];
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    public function funnel(): array
    {
        return [
            ['key' => 'submitted', 'label' => 'Mendaftar', 'count' => 412],
            ['key' => 'verified', 'label' => 'Berkas terverifikasi', 'count' => 338],
            ['key' => 'accepted', 'label' => 'Diterima', 'count' => 180],
            ['key' => 'registered', 'label' => 'Daftar ulang', 'count' => 96],
        ];
    }

    /**
     * @return list<array{id: int, name: string, opensOn: string, closesOn: string, status: string, applicants: int}>
     */
    public function waves(): array
    {
        return [
            ['id' => 1, 'name' => 'Gelombang 1', 'opensOn' => '1 Januari 2027', 'closesOn' => '31 Maret 2027', 'status' => 'closed', 'applicants' => 248],
            ['id' => 2, 'name' => 'Gelombang 2', 'opensOn' => '1 April 2027', 'closesOn' => '30 Juni 2027', 'status' => 'open', 'applicants' => 164],
            ['id' => 3, 'name' => 'Gelombang 3', 'opensOn' => '1 Juli 2027', 'closesOn' => '15 Juli 2027', 'status' => 'upcoming', 'applicants' => 0],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function paths(): array
    {
        return [
            ['value' => 'zonasi', 'label' => 'Zonasi'],
            ['value' => 'prestasi', 'label' => 'Prestasi'],
            ['value' => 'afirmasi', 'label' => 'Afirmasi'],
            ['value' => 'mutasi', 'label' => 'Mutasi'],
        ];
    }

    /**
     * @return list<array{id: int, number: string, name: string, origin: string, path: string, pathLabel: string, status: string, submittedOn: string}>
     */
    public function applicants(): array
    {
        $rows = [
            ['Nadia Putri Anggraini', 'SMPN 3 Bandung', 'zonasi', 'Zonasi', 'verified', '12 Januari 2027'],
            ['Rafi Maulana', 'SMP Al-Azhar', 'prestasi', 'Prestasi', 'verified', '14 Januari 2027'],
            ['Salsabila Azzahra', 'SMPN 1 Cimahi', 'zonasi', 'Zonasi', 'submitted', '20 Januari 2027'],
            ['Dika Prasetyo', 'SMPN 12 Bandung', 'afirmasi', 'Afirmasi', 'revision', '22 Januari 2027'],
            ['Anindya Kirana', 'SMP Pasundan 2', 'prestasi', 'Prestasi', 'accepted', '3 Februari 2027'],
            ['Muhammad Fikri', 'SMPN 5 Bandung', 'mutasi', 'Mutasi', 'rejected', '5 Februari 2027'],
            ['Tiara Ramadhani', 'SMPN 9 Bandung', 'zonasi', 'Zonasi', 'verified', '8 Februari 2027'],
            ['Galih Permana', 'SMP Taruna Bakti', 'afirmasi', 'Afirmasi', 'submitted', '10 Februari 2027'],
        ];

        return array_map(fn (array $row, int $index): array => [
            'id' => $index + 1,
            'number' => sprintf('PPDB-27-%04d', $index + 1),
            'name' => $row[0],
            'origin' => $row[1],
            'path' => $row[2],
            'pathLabel' => $row[3],
            'status' => $row[4],
            'submittedOn' => $row[5],
        ], $rows, array_keys($rows));
    }

    /**
     * @return array{capacity: int, accepted: int, waitlist: int}
     */
    public function quota(): array
    {
        return ['capacity' => 180, 'accepted' => 6, 'waitlist' => 2];
    }

    /**
     * @return list<array{id: int, rank: int, name: string, path: string, score: float, decision: string}>
     */
    public function candidates(): array
    {
        $rows = [
            ['Anindya Kirana', 'Prestasi', 94.5, 'accepted'],
            ['Rafi Maulana', 'Prestasi', 92.0, 'accepted'],
            ['Nadia Putri Anggraini', 'Zonasi', 88.4, 'accepted'],
            ['Tiara Ramadhani', 'Zonasi', 86.1, 'pending'],
            ['Dika Prasetyo', 'Afirmasi', 83.7, 'pending'],
            ['Salsabila Azzahra', 'Zonasi', 79.2, 'waitlist'],
            ['Galih Permana', 'Afirmasi', 76.8, 'waitlist'],
            ['Muhammad Fikri', 'Mutasi', 61.5, 'rejected'],
        ];

        return array_map(fn (array $row, int $index): array => [
            'id' => $index + 1,
            'rank' => $index + 1,
            'name' => $row[0],
            'path' => $row[1],
            'score' => $row[2],
            'decision' => $row[3],
        ], $rows, array_keys($rows));
    }
}
