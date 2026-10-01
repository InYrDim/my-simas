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
        ],
    ],

    'guru' => [
        'label' => 'Guru',
        'description' => 'Pengajar: mencatat kegiatan belajar mengajar dan absensi kelasnya.',
        'permissions' => [],
    ],

    'staf-tu' => [
        'label' => 'Staf/TU',
        'description' => 'Tata usaha: mengurus data administrasi sekolah sehari-hari.',
        'permissions' => [],
    ],
];
