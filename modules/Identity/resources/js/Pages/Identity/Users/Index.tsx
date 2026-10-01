import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

import {
    create as usersCreate,
    edit as editUser,
    invite as usersInvite,
} from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

import { DataTable, EmptyState, PageHeader } from '@shared/components/page-parts';
import TenantShell from '@shared/components/TenantShell';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import type { ManagedUser } from '../../../types/ManagedUser';

interface IndexProps {
    users: ManagedUser[];
}

/** Status word: deactivated wins, then not-yet-activated, then active. */
function UserStatus({ user }: { user: ManagedUser }) {
    if (!user.isActive) {
        return <Badge variant="destructive">Nonaktif</Badge>;
    }

    if (!user.hasPassword) {
        return <Badge variant="outline">Menunggu aktivasi</Badge>;
    }

    return <Badge>Aktif</Badge>;
}

/**
 * User list (school admin) as a table: name, email, roles, status. Entry
 * point to create / invite / edit (deactivate, reactivate, send-reset).
 */
export default function UsersIndex({ users }: IndexProps) {
    const [query, setQuery] = useState('');

    const needle = query.trim().toLowerCase();
    const rows = users.filter(
        (user) =>
            needle === '' ||
            user.name.toLowerCase().includes(needle) ||
            user.email.toLowerCase().includes(needle),
    );

    return (
        <TenantShell width="max-w-6xl">
            <Head title="Pengguna" />

            <PageHeader
                title="Daftar Pengguna"
                description="Kelola akun staf sekolah: profil, peran, dan status aktif."
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={usersInvite.url()}>Undang</Link>
                        </Button>
                        <Button asChild>
                            <Link href={usersCreate.url()}>Tambah Pengguna</Link>
                        </Button>
                    </>
                }
            />

            <div className="mt-8">
                {users.length === 0 ? (
                    <EmptyState>Belum ada pengguna selain Anda.</EmptyState>
                ) : (
                    <>
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <Input
                                type="search"
                                aria-label="Cari nama atau email"
                                placeholder="Cari nama atau email"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                                className="sm:max-w-xs"
                            />
                            <p className="text-sm text-muted-foreground">
                                {rows.length} dari {users.length} pengguna
                            </p>
                        </div>

                        {rows.length === 0 ? (
                            <EmptyState>Tidak ada pengguna yang cocok.</EmptyState>
                        ) : (
                            <DataTable head={['Nama', 'Email', 'Peran', 'Status', '']}>
                                {rows.map((user) => (
                                    <TableRow key={user.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={editUser.url({ userId: user.id })}
                                                className="hover:underline"
                                            >
                                                {user.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {user.email}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {user.roleLabels.length === 0 ? (
                                                    <Badge variant="outline">Tanpa peran</Badge>
                                                ) : (
                                                    user.roleLabels.map((label) => (
                                                        <Badge key={label} variant="secondary">
                                                            {label}
                                                        </Badge>
                                                    ))
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <UserStatus user={user} />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button asChild size="sm" variant="outline">
                                                <Link href={editUser.url({ userId: user.id })}>
                                                    Ubah
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        )}
                    </>
                )}
            </div>
        </TenantShell>
    );
}
