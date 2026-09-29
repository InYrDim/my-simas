<?php

/**
 * Default roles seeded for every NEW tenant via the SeedDefaultRoles
 * listener on Platform's TenantCreated event.
 *
 * Keys are Spatie role names — machine names (lowercase, hyphenated),
 * stable identifiers in code, DB rows, and exports. Values:
 * - 'label'   → display name in the UI (rename freely; DB untouched)
 * - 'permissions' → permission names attached on seed. Must be
 *   registered via PermissionRegistry (Identity's provider registers
 *   identity.users.*); TenantRoles::ensure() syncs missing global rows
 *   before attaching. Empty set = role without permissions; business
 *   modules extend this later via their own config/listeners.
 */
return [
    'admin-sekolah' => [
        'label' => 'Admin Sekolah',
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
        'permissions' => [],
    ],

    'staf-tu' => [
        'label' => 'Staf/TU',
        'permissions' => [],
    ],
];
