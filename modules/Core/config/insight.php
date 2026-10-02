<?php

/**
 * Statistik & Laporan entries that are announced but not built yet. They
 * are shown as "Segera hadir" and are labels only: an entry disappears as
 * soon as a module registers a report, figure or panel with the same key.
 */
return [
    'upcoming' => [
        'reports' => [
            ['key' => 'schedule', 'group' => 'Akademik', 'name' => 'Jadwal Pelajaran', 'description' => 'Jadwal mingguan per kelas atau per guru.', 'order' => 40],
            ['key' => 'attendance-monthly', 'group' => 'Kehadiran', 'name' => 'Rekap Kehadiran Bulanan', 'description' => 'Hadir, sakit, izin, dan alpa setiap siswa.', 'order' => 50],
            ['key' => 'attendance-class', 'group' => 'Kehadiran', 'name' => 'Kehadiran per Kelas', 'description' => 'Persentase kehadiran tiap kelas dalam satu periode.', 'order' => 60],
            ['key' => 'ppdb-applicants', 'group' => 'Penerimaan (PPDB)', 'name' => 'Daftar Pendaftar PPDB', 'description' => 'Pendaftar menurut jalur dan status verifikasi.', 'order' => 70],
            ['key' => 'ppdb-result', 'group' => 'Penerimaan (PPDB)', 'name' => 'Hasil Seleksi PPDB', 'description' => 'Peringkat, keputusan, dan daftar cadangan.', 'order' => 80],
        ],
        'figures' => [
            ['key' => 'attendance-rate', 'label' => 'Rata-rata kehadiran'],
        ],
        'panels' => [
            ['key' => 'attendance-trend', 'title' => 'Kehadiran 6 bulan terakhir'],
        ],
    ],
];
