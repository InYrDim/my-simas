<?php

namespace Modules\Core\App\Infrastructure\Mock;

/**
 * Static sample data for the Statistik and Laporan mockup pages. Grade
 * names and class counts come from MasterMockData so every jenjang
 * previews consistently. Nothing here is persisted.
 */
final class InsightMockData
{
    public function __construct(private readonly MasterMockData $master) {}

    /**
     * @return array{totals: array<string, int|float>, byGrade: list<array{name: string, students: int}>, gender: array{male: int, female: int}, attendance: list<array{month: string, percent: float}>}
     */
    public function statistics(): array
    {
        $counts = [212, 218, 212, 205, 198, 190];
        $grades = $this->master->grades();
        $byGrade = [];

        foreach ($grades as $index => $grade) {
            $byGrade[] = ['name' => $grade['name'], 'students' => $counts[$index % count($counts)]];
        }

        $students = array_sum(array_column($byGrade, 'students'));
        $male = (int) round($students * 0.49);

        return [
            'totals' => [
                'students' => $students,
                'teachers' => 48,
                'classes' => array_sum(array_column($grades, 'classes')),
                'attendance' => 94.6,
            ],
            'byGrade' => $byGrade,
            'gender' => ['male' => $male, 'female' => $students - $male],
            'attendance' => [
                ['month' => 'Apr', 'percent' => 93.2],
                ['month' => 'Mei', 'percent' => 91.8],
                ['month' => 'Jun', 'percent' => 89.5],
                ['month' => 'Jul', 'percent' => 95.1],
                ['month' => 'Agu', 'percent' => 95.8],
                ['month' => 'Sep', 'percent' => 94.6],
            ],
        ];
    }

    /**
     * @return array{groups: list<array{title: string, reports: list<array{key: string, name: string, description: string, formats: list<string>}>}>, recent: list<array{name: string, period: string, createdBy: string, createdOn: string, status: string}>}
     */
    public function reports(): array
    {
        return [
            'groups' => [
                [
                    'title' => 'Kesiswaan',
                    'reports' => [
                        ['key' => 'student-list', 'name' => 'Daftar Siswa per Kelas', 'description' => 'Siswa aktif beserta NIS, NISN, dan jenis kelamin.', 'formats' => ['PDF', 'Excel']],
                        ['key' => 'student-mutation', 'name' => 'Mutasi Siswa', 'description' => 'Siswa masuk, pindah, dan lulus pada periode terpilih.', 'formats' => ['PDF', 'Excel']],
                    ],
                ],
                [
                    'title' => 'Akademik',
                    'reports' => [
                        ['key' => 'teaching-load', 'name' => 'Beban Mengajar Guru', 'description' => 'Jam mengajar dan kelas yang diampu setiap guru.', 'formats' => ['PDF', 'Excel']],
                        ['key' => 'schedule', 'name' => 'Jadwal Pelajaran', 'description' => 'Jadwal mingguan per kelas atau per guru.', 'formats' => ['PDF']],
                    ],
                ],
                [
                    'title' => 'Kehadiran',
                    'reports' => [
                        ['key' => 'attendance-monthly', 'name' => 'Rekap Kehadiran Bulanan', 'description' => 'Hadir, sakit, izin, dan alpa setiap siswa.', 'formats' => ['PDF', 'Excel']],
                        ['key' => 'attendance-class', 'name' => 'Kehadiran per Kelas', 'description' => 'Persentase kehadiran tiap kelas dalam satu periode.', 'formats' => ['Excel']],
                    ],
                ],
                [
                    'title' => 'Penerimaan (PPDB)',
                    'reports' => [
                        ['key' => 'ppdb-applicants', 'name' => 'Daftar Pendaftar PPDB', 'description' => 'Pendaftar menurut jalur dan status verifikasi.', 'formats' => ['Excel']],
                        ['key' => 'ppdb-result', 'name' => 'Hasil Seleksi PPDB', 'description' => 'Peringkat, keputusan, dan daftar cadangan.', 'formats' => ['PDF', 'Excel']],
                    ],
                ],
            ],
            'recent' => [
                ['name' => 'Rekap Kehadiran Bulanan', 'period' => 'September 2026', 'createdBy' => 'Admin Sekolah', 'createdOn' => '1 Oktober 2026', 'status' => 'ready'],
                ['name' => 'Daftar Siswa per Kelas', 'period' => 'Semester Ganjil 2026/2027', 'createdBy' => 'Staf/TU', 'createdOn' => '28 September 2026', 'status' => 'ready'],
                ['name' => 'Beban Mengajar Guru', 'period' => 'Semester Ganjil 2026/2027', 'createdBy' => 'Admin Sekolah', 'createdOn' => '25 September 2026', 'status' => 'failed'],
            ],
        ];
    }
}
