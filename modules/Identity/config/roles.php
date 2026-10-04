<?php

/**
 * Default roles seeded for every NEW tenant via the SeedDefaultRoles
 * listener on Platform's TenantCreated event.
 *
 * Keys are Spatie role names — machine names (lowercase, hyphenated),
 * stable identifiers in code, DB rows, and exports. Values:
 * - 'label'   → display name in the UI (rename freely; DB untouched)
 * - 'description' → one plain sentence shown on the school's role list
 * - 'permissions' → permission names attached on seed. Must be
 *   registered via PermissionRegistry (Identity's provider registers
 *   identity.users.*); TenantRoles::ensure() syncs missing global rows
 *   before attaching. Empty set = role without permissions; business
 *   modules extend this later via their own config/listeners.
 */
return [
    'admin-sekolah' => [
        'label' => 'Admin Sekolah',
        'description' => 'Pemilik akun sekolah: mengelola pengguna, peran, dan pengaturan sekolah.',
        'permissions' => [
            'identity.users.view',
            'identity.users.create',
            'identity.users.update',
            'identity.users.deactivate',
            'identity.users.sendReset',
            'core.master.view',
            'core.master.manage',
            'core.academic.view',
            'core.academic.manage',
            'core.integration.manage',
            'attendance.view',
            'attendance.daily.record',
            'attendance.lesson.record',
            'attendance.settings.manage',
            'ppdb.view',
            'ppdb.applicants.manage',
            'ppdb.selection.manage',
            'ppdb.settings.manage',
        ],
    ],

    'guru' => [
        'label' => 'Guru',
        'description' => 'Pengajar: mencatat kegiatan belajar mengajar dan absensi jam pelajaran di kelasnya.',
        'permissions' => [
            'core.me.view',
            'core.teaching.view',
            'core.master.view',
            'core.academic.view',
            'attendance.class.record',
        ],
    ],

    'staf-tu' => [
        'label' => 'Staf/TU',
        'description' => 'Tata usaha: mengurus data administrasi sekolah sehari-hari.',
        'permissions' => [
            'core.me.view',
            'core.master.view',
            'core.academic.view',
            'attendance.view',
            'attendance.daily.record',
            'ppdb.view',
            'ppdb.applicants.manage',
        ],
    ],

    'siswa' => [
        'label' => 'Siswa',
        'description' => 'Peserta didik: masuk dengan NIS untuk layanan siswa.',
        'permissions' => ['core.me.view', 'attendance.qr.show', 'attendance.mine.view'],
    ],
];
