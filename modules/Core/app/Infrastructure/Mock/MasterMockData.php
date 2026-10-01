<?php

namespace Modules\Core\App\Infrastructure\Mock;

/**
 * Static, mutually-consistent sample data for the master-data mockup
 * pages (school profile, academic years, classes, subjects, teachers,
 * students, ...). Nothing here is persisted; the DB phase replaces this
 * class behind the controller with real repositories.
 *
 * The school level (sd|smp|sma|smk) drives labels, grades and majors so
 * every jenjang can be previewed.
 */
final class MasterMockData
{
    public const LEVELS = [
        'sd' => 'SD/MI',
        'smp' => 'SMP/MTs',
        'sma' => 'SMA/MA',
        'smk' => 'SMK',
    ];

    public function __construct(private readonly string $level = 'sma') {}

    public static function normaliseLevel(?string $level): string
    {
        return array_key_exists((string) $level, self::LEVELS) ? (string) $level : 'sma';
    }

    /**
     * @return array<string, mixed>
     */
    public function school(string $name, string $code, string $timezone): array
    {
        return [
            'name' => $name,
            'code' => $code,
            'timezone' => $timezone,
            'npsn' => '20512345',
            'level' => $this->level,
            'levelLabel' => self::LEVELS[$this->level],
            'levelOptions' => self::options(self::LEVELS),
            'ownership' => 'Swasta',
            'accreditation' => 'A',
            'address' => 'Jl. Pendidikan No. 12, Kec. Cibeunying, Kota Bandung 40121',
            'phone' => '(022) 555-0123',
            'email' => 'info@sekolah-contoh.sch.id',
            'headmaster' => 'Dra. Siti Rahmawati, M.Pd.',
            'headmasterNip' => '196804121994032005',
            'hasMajors' => in_array($this->level, ['sma', 'smk'], true),
            'homeroomLabel' => $this->level === 'sd' ? 'Guru Kelas' : 'Wali Kelas',
        ];
    }

    /**
     * @return array{years: list<array<string, mixed>>}
     */
    public function academicYears(): array
    {
        return ['years' => [
            [
                'id' => 3, 'name' => '2026/2027', 'curriculum' => 'Kurikulum Merdeka',
                'start' => '2026-07-13', 'end' => '2027-06-26', 'status' => 'draft',
                'semesters' => [
                    ['name' => 'Ganjil', 'start' => '2026-07-13', 'end' => '2026-12-19'],
                    ['name' => 'Genap', 'start' => '2027-01-04', 'end' => '2027-06-26'],
                ],
            ],
            [
                'id' => 2, 'name' => '2025/2026', 'curriculum' => 'Kurikulum Merdeka',
                'start' => '2025-07-14', 'end' => '2026-06-27', 'status' => 'active',
                'semesters' => [
                    ['name' => 'Ganjil', 'start' => '2025-07-14', 'end' => '2025-12-20'],
                    ['name' => 'Genap', 'start' => '2026-01-05', 'end' => '2026-06-27'],
                ],
            ],
            [
                'id' => 1, 'name' => '2024/2025', 'curriculum' => 'Kurikulum Merdeka',
                'start' => '2024-07-15', 'end' => '2025-06-28', 'status' => 'archived',
                'semesters' => [
                    ['name' => 'Ganjil', 'start' => '2024-07-15', 'end' => '2024-12-21'],
                    ['name' => 'Genap', 'start' => '2025-01-06', 'end' => '2025-06-28'],
                ],
            ],
        ]];
    }

    /**
     * Grades (tingkat) for the current level.
     *
     * @return list<array{id: int, name: string, order: int, classes: int}>
     */
    public function grades(): array
    {
        [$first, $last, $roman] = match ($this->level) {
            'sd' => [1, 6, false],
            'smp' => [7, 9, false],
            default => [10, 12, true],
        };

        $rows = [];

        for ($grade = $first; $grade <= $last; $grade++) {
            $rows[] = [
                'id' => $grade,
                'name' => $roman ? $this->roman($grade) : (string) $grade,
                'order' => $grade,
                'classes' => $this->level === 'sd' || $this->level === 'smp' ? 3 : count($this->majors()) * 2,
            ];
        }

        return $rows;
    }

    /**
     * Majors (peminatan / kompetensi keahlian). Empty for SD and SMP.
     *
     * @return list<array{id: int, code: string, name: string, kind: string, concentrations: list<string>}>
     */
    public function majors(): array
    {
        return match ($this->level) {
            'sma' => [
                ['id' => 1, 'code' => 'IPA', 'name' => 'Matematika dan Ilmu Pengetahuan Alam', 'kind' => 'Peminatan', 'concentrations' => []],
                ['id' => 2, 'code' => 'IPS', 'name' => 'Ilmu Pengetahuan Sosial', 'kind' => 'Peminatan', 'concentrations' => []],
                ['id' => 3, 'code' => 'BHS', 'name' => 'Bahasa dan Budaya', 'kind' => 'Peminatan', 'concentrations' => []],
            ],
            'smk' => [
                ['id' => 1, 'code' => 'TKJ', 'name' => 'Teknik Komputer dan Jaringan', 'kind' => 'Kompetensi Keahlian', 'concentrations' => ['Administrasi Infrastruktur Jaringan']],
                ['id' => 2, 'code' => 'RPL', 'name' => 'Rekayasa Perangkat Lunak', 'kind' => 'Kompetensi Keahlian', 'concentrations' => ['Pemrograman Web', 'Pemrograman Mobile']],
                ['id' => 3, 'code' => 'AKL', 'name' => 'Akuntansi dan Keuangan Lembaga', 'kind' => 'Kompetensi Keahlian', 'concentrations' => []],
            ],
            default => [],
        };
    }

    /**
     * Class groups (rombel) of the active academic year.
     *
     * @return list<array<string, mixed>>
     */
    public function classes(): array
    {
        $teachers = $this->teachers();
        $rooms = $this->rooms();
        $rows = [];
        $id = 1;

        foreach ($this->grades() as $grade) {
            $groups = $this->majors() === []
                ? [['code' => null, 'id' => null]]
                : array_map(fn (array $major): array => ['code' => $major['code'], 'id' => $major['id']], $this->majors());

            foreach ($groups as $group) {
                foreach ([1, 2] as $number) {
                    if ($this->majors() === [] && $number > 2) {
                        continue;
                    }

                    $name = trim($grade['name'].' '.($group['code'] ?? '').' '.$number);
                    $rows[] = [
                        'id' => $id,
                        'name' => $name,
                        'gradeId' => $grade['id'],
                        'grade' => $grade['name'],
                        'major' => $group['code'],
                        'homeroom' => $teachers[($id - 1) % count($teachers)]['name'],
                        'homeroomId' => $teachers[($id - 1) % count($teachers)]['id'],
                        'room' => $rooms[($id - 1) % 6]['name'],
                        'students' => 28 + (($id * 3) % 8),
                        'yearId' => 2,
                    ];
                    $id++;
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function subjects(): array
    {
        $rows = [
            ['Pendidikan Agama dan Budi Pekerti', 'PAI', 'Umum', 75],
            ['Pendidikan Pancasila', 'PPKN', 'Umum', 75],
            ['Bahasa Indonesia', 'BIN', 'Umum', 75],
            ['Matematika', 'MTK', 'Umum', 70],
            ['Bahasa Inggris', 'BIG', 'Umum', 75],
            ['Pendidikan Jasmani dan Olahraga', 'PJOK', 'Umum', 75],
            ['Seni Budaya', 'SBK', 'Umum', 75],
            ['Bahasa Sunda', 'BSD', 'Muatan Lokal', 75],
        ];

        $rows = match ($this->level) {
            'sd' => [...$rows, ['IPAS', 'IPAS', 'Umum', 70]],
            'smp' => [...$rows, ['IPA', 'IPA', 'Umum', 70], ['IPS', 'IPS', 'Umum', 70], ['Informatika', 'INF', 'Umum', 75]],
            'sma' => [...$rows, ['Fisika', 'FIS', 'Peminatan', 70], ['Kimia', 'KIM', 'Peminatan', 70], ['Ekonomi', 'EKO', 'Peminatan', 72]],
            default => [...$rows, ['Dasar-dasar Program Keahlian', 'DPK', 'Kejuruan', 75], ['Produktif Kompetensi Keahlian', 'PKK', 'Kejuruan', 78]],
        };

        $teachers = $this->teachers();

        return array_map(fn (array $row, int $index): array => [
            'id' => $index + 1,
            'name' => $row[0],
            'code' => $row[1],
            'group' => $row[2],
            'kkm' => $row[3],
            'grades' => $this->level === 'sd' ? 'Kelas 1–6' : ($this->level === 'smp' ? 'Kelas 7–9' : 'Kelas X–XII'),
            'teacher' => $teachers[$index % count($teachers)]['name'],
        ], $rows, array_keys($rows));
    }

    /**
     * Teaching assignments: teacher × subject × class.
     *
     * @return list<array{teacher: string, subject: string, class: string, hours: int}>
     */
    public function assignments(): array
    {
        $subjects = $this->subjects();
        $classes = $this->classes();
        $teachers = $this->teachers();
        $rows = [];

        foreach (array_slice($classes, 0, 4) as $classIndex => $class) {
            foreach (array_slice($subjects, 0, 5) as $subjectIndex => $subject) {
                $rows[] = [
                    'teacher' => $teachers[($classIndex + $subjectIndex) % count($teachers)]['name'],
                    'subject' => $subject['name'],
                    'class' => $class['name'],
                    'hours' => 2 + ($subjectIndex % 3),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function teachers(): array
    {
        $names = [
            ['Dra. Siti Rahmawati, M.Pd.', 'PNS', 'Kepala Sekolah', true],
            ['Budi Santoso, S.Pd.', 'PNS', 'Guru Mapel', true],
            ['Rina Wulandari, S.Si.', 'GTY', 'Guru Mapel', true],
            ['Agus Pratama, S.Kom.', 'GTY', 'Guru Mapel', false],
            ['Dewi Lestari, S.Pd.', 'PNS', 'Guru Mapel', true],
            ['Hendra Gunawan, S.E.', 'GTT', 'Guru Mapel', false],
            ['Fitri Handayani, S.Pd.', 'GTY', 'Guru Mapel', true],
            ['Yusuf Maulana, S.Ag.', 'PNS', 'Guru Mapel', true],
            ['Lilis Suryani, A.Md.', 'Honorer', 'Tenaga Kependidikan', false],
            ['Dedi Kurniawan, S.Pd.', 'GTT', 'Guru Mapel', false],
        ];

        return array_map(fn (array $row, int $index): array => [
            'id' => $index + 1,
            'name' => $row[0],
            'nip' => $row[1] === 'PNS' ? '1980'.str_pad((string) (1000 + $index * 37), 4, '0', STR_PAD_LEFT).'20050100'.($index % 9 + 1) : '—',
            'nuptk' => '5543'.str_pad((string) (7700000 + $index * 811), 8, '0', STR_PAD_LEFT),
            'employment' => $row[1],
            'duty' => $row[2],
            'hasAccount' => $row[3],
            'email' => $row[3] ? 'guru'.($index + 1).'@sekolah-contoh.sch.id' : null,
        ], $names, array_keys($names));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function students(): array
    {
        $names = [
            'Aditya Nugraha', 'Alya Putri Rahma', 'Bagas Prasetyo', 'Bunga Citra Lestari',
            'Cahya Ramadhan', 'Dimas Aryo Wibowo', 'Eka Safitri', 'Farhan Hidayat',
            'Gita Permata Sari', 'Hafizh Maulana', 'Indah Kusuma Dewi', 'Joko Susilo',
            'Kirana Anindya', 'Lukman Hakim', 'Maya Anggraini', 'Naufal Rizki',
            'Olivia Tan', 'Putra Mahendra', 'Qonita Azzahra', 'Rizal Firmansyah',
            'Salsabila Nur', 'Taufik Hidayatullah', 'Umi Kalsum', 'Vino Aditama',
        ];
        $statuses = ['active', 'active', 'active', 'active', 'active', 'transferred', 'graduated', 'active'];
        $classes = $this->classes();

        return array_map(function (string $name, int $index) use ($classes, $statuses): array {
            $class = $classes[$index % count($classes)];
            $status = $statuses[$index % count($statuses)];

            return [
                'id' => $index + 1,
                'name' => $name,
                'nis' => (string) (240100 + $index),
                'nisn' => '00'.(81234500 + $index * 17),
                'gender' => $index % 2 === 0 ? 'L' : 'P',
                'birth' => '2009-'.str_pad((string) ($index % 12 + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) ($index % 27 + 1), 2, '0', STR_PAD_LEFT),
                'class' => $status === 'active' ? $class['name'] : null,
                'classId' => $status === 'active' ? $class['id'] : null,
                'status' => $status,
                'guardian' => 'Bpk./Ibu '.explode(' ', $name)[0],
                'guardianPhone' => '0812-5550-'.str_pad((string) (1000 + $index), 4, '0', STR_PAD_LEFT),
                'hasAccount' => $index % 5 === 0,
            ];
        }, $names, array_keys($names));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rooms(): array
    {
        $rows = [
            ['R-101', 'Ruang Kelas 101', 'Kelas', 36], ['R-102', 'Ruang Kelas 102', 'Kelas', 36],
            ['R-103', 'Ruang Kelas 103', 'Kelas', 36], ['R-201', 'Ruang Kelas 201', 'Kelas', 32],
            ['R-202', 'Ruang Kelas 202', 'Kelas', 32], ['R-203', 'Ruang Kelas 203', 'Kelas', 32],
            ['LAB-KOM', 'Laboratorium Komputer', 'Laboratorium', 40], ['LAB-IPA', 'Laboratorium IPA', 'Laboratorium', 30],
            ['AULA', 'Aula Serbaguna', 'Aula', 300], ['PERPUS', 'Perpustakaan', 'Perpustakaan', 60],
        ];

        return array_map(fn (array $row, int $index): array => [
            'id' => $index + 1,
            'code' => $row[0],
            'name' => $row[1],
            'type' => $row[2],
            'capacity' => $row[3],
            'status' => $index === 5 ? 'maintenance' : 'active',
        ], $rows, array_keys($rows));
    }

    /**
     * Daily bell schedule per weekday.
     *
     * @return list<array{day: string, slots: list<array{order: int|null, start: string, end: string, type: string}>}>
     */
    public function periods(): array
    {
        $weekday = [
            ['order' => null, 'start' => '07:00', 'end' => '07:15', 'type' => 'Pembiasaan'],
            ['order' => 1, 'start' => '07:15', 'end' => '08:00', 'type' => 'Pelajaran'],
            ['order' => 2, 'start' => '08:00', 'end' => '08:45', 'type' => 'Pelajaran'],
            ['order' => 3, 'start' => '08:45', 'end' => '09:30', 'type' => 'Pelajaran'],
            ['order' => null, 'start' => '09:30', 'end' => '09:45', 'type' => 'Istirahat'],
            ['order' => 4, 'start' => '09:45', 'end' => '10:30', 'type' => 'Pelajaran'],
            ['order' => 5, 'start' => '10:30', 'end' => '11:15', 'type' => 'Pelajaran'],
            ['order' => 6, 'start' => '11:15', 'end' => '12:00', 'type' => 'Pelajaran'],
            ['order' => null, 'start' => '12:00', 'end' => '12:45', 'type' => 'Istirahat'],
            ['order' => 7, 'start' => '12:45', 'end' => '13:30', 'type' => 'Pelajaran'],
            ['order' => 8, 'start' => '13:30', 'end' => '14:15', 'type' => 'Pelajaran'],
        ];

        $monday = [
            ['order' => null, 'start' => '07:00', 'end' => '07:45', 'type' => 'Upacara'],
            ...array_slice($weekday, 2),
        ];

        $friday = array_slice($weekday, 0, 8);

        return [
            ['day' => 'Senin', 'slots' => $monday],
            ['day' => 'Selasa', 'slots' => $weekday],
            ['day' => 'Rabu', 'slots' => $weekday],
            ['day' => 'Kamis', 'slots' => $weekday],
            ['day' => 'Jumat', 'slots' => $friday],
            ['day' => 'Sabtu', 'slots' => array_slice($weekday, 0, 6)],
        ];
    }

    /**
     * @return list<array{id: int, date: string, endDate: string|null, title: string, category: string}>
     */
    public function calendarEvents(): array
    {
        $rows = [
            ['2025-07-14', null, 'Hari pertama tahun ajaran', 'activity'],
            ['2025-08-17', null, 'Hari Kemerdekaan RI', 'holiday'],
            ['2025-09-05', null, 'Maulid Nabi Muhammad SAW', 'holiday'],
            ['2025-10-20', '2025-10-24', 'Penilaian Tengah Semester', 'exam'],
            ['2025-11-10', null, 'Hari Pahlawan — upacara', 'activity'],
            ['2025-12-08', '2025-12-13', 'Penilaian Akhir Semester Ganjil', 'exam'],
            ['2025-12-20', null, 'Pembagian rapor semester Ganjil', 'activity'],
            ['2025-12-22', '2026-01-03', 'Libur semester Ganjil', 'holiday'],
            ['2026-01-05', null, 'Awal semester Genap', 'activity'],
            ['2026-03-16', '2026-03-20', 'Penilaian Tengah Semester Genap', 'exam'],
            ['2026-05-04', '2026-05-15', 'Asesmen Sumatif Akhir Jenjang', 'exam'],
            ['2026-06-27', null, 'Pembagian rapor akhir tahun', 'activity'],
        ];

        return array_map(fn (array $row, int $index): array => [
            'id' => $index + 1,
            'date' => $row[0],
            'endDate' => $row[1],
            'title' => $row[2],
            'category' => $row[3],
        ], $rows, array_keys($rows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function extracurriculars(): array
    {
        $teachers = $this->teachers();
        $students = $this->students();
        $rows = [
            ['Pramuka', 'Jumat 14.30–16.00', 'Wajib'], ['Paskibra', 'Selasa 14.30–16.00', 'Pilihan'],
            ['PMR', 'Rabu 14.30–16.00', 'Pilihan'], ['Futsal', 'Kamis 15.00–17.00', 'Pilihan'],
            ['Seni Tari', 'Sabtu 08.00–10.00', 'Pilihan'],
        ];

        return array_map(fn (array $row, int $index): array => [
            'id' => $index + 1,
            'name' => $row[0],
            'schedule' => $row[1],
            'kind' => $row[2],
            'coach' => $teachers[($index + 1) % count($teachers)]['name'],
            'members' => 18 + $index * 7,
            'memberList' => array_slice($students, $index * 3, 6),
        ], $rows, array_keys($rows));
    }

    /**
     * @param  array<string, string>  $map
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $map): array
    {
        $options = [];

        foreach ($map as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    private function roman(int $number): string
    {
        return [10 => 'X', 11 => 'XI', 12 => 'XII'][$number] ?? (string) $number;
    }
}
