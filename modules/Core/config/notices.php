<?php

/**
 * WhatsApp notices that are announced but not built yet. They are shown
 * as "Segera hadir" on Integrasi › WhatsApp and are labels only: an entry
 * disappears as soon as a module registers a kind with the same key.
 */
return [
    'upcoming' => [
        ['key' => 'attendance.absent', 'title' => 'Siswa tidak hadir', 'description' => 'Dikirim saat siswa ditandai sakit, izin, atau alpa.', 'recipient' => 'Wali murid'],
        ['key' => 'attendance.gate', 'title' => 'Siswa masuk dan pulang', 'description' => 'Dikirim saat siswa tercatat di gerbang sekolah.', 'recipient' => 'Wali murid'],
        ['key' => 'ppdb.result', 'title' => 'Hasil seleksi PPDB', 'description' => 'Dikirim saat hasil seleksi diumumkan.', 'recipient' => 'Pendaftar'],
    ],
];
