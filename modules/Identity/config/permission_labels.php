<?php

/**
 * Plain-language meaning of each permission name, shown on the school's
 * Sistem pages (Peran, Izin). Names come from the owning module's
 * PermissionRegistry registration; a permission without an entry here is
 * shown by its machine name, so a module can ship before it is labelled.
 */
return [
    'identity.users.view' => 'Melihat daftar pengguna',
    'identity.users.create' => 'Menambah dan mengundang pengguna',
    'identity.users.update' => 'Mengubah profil dan peran pengguna',
    'identity.users.deactivate' => 'Menonaktifkan dan mengaktifkan kembali akun',
    'identity.users.sendReset' => 'Mengirim tautan atur ulang kata sandi',
];
