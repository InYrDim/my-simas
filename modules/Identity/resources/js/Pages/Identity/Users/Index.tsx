import { Head, Link } from '@inertiajs/react';

import {
    create as usersCreate,
    edit as editUser,
    index as usersIndex,
    invite as usersInvite,
} from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

import ListPager, { useListFilters } from '@shared/components/ListPager';
import type { Pagination } from '@shared/components/ListPager';
import {
    DataTable,
    EmptyState,
    OptionSelect,
    PageHeader,
} from '@shared/components/page-parts';
import TenantShell from '@shared/components/TenantShell';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import type { ManagedUser } from '../../../types/ManagedUser';

interface IndexProps {
    users: ManagedUser[];
    roleLabels: Record<string, string>;
    filters: { q: string; role: string };
    pagination: Pagination;
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
 * User list (school admin) as a table: name, how the account signs in
 * (username, email), roles, status. Searched, filtered by role and paged
 * on the server. Entry point to create / invite / edit.
 */
export default function UsersIndex({
    users,
    roleLabels,
    filters: initial,
    pagination,
}: IndexProps) {
    const url = usersIndex.url();
    const { filters, set } = useListFilters(url, initial);
    const filtered = initial.q !== '' || initial.role !== '';

    return (
        <TenantShell width="max-w-6xl">
            <Head title="Pengguna" />

            <PageHeader
                title="Daftar Pengguna"
                description="Kelola akun sekolah: profil, peran, dan status aktif."
                actions={
                    <>
                        <Button asChild variant="outline">
                            <Link href={usersInvite.url()}>Undang</Link>
                        </Button>
                        <Button asChild>
                            <Link href={usersCreate.url()}>
                                Tambah Pengguna
                            </Link>
                        </Button>
                    </>
                }
            />

            <div className="mt-8">
                <div className="mb-4 grid gap-3 sm:grid-cols-3">
                    <Input
                        type="search"
                        aria-label="Cari pengguna"
                        placeholder="Cari nama, email, atau NIS/NIP"
                        value={filters.q}
                        onChange={(event) => set('q', event.target.value)}
                    />
                    <OptionSelect
                        label="Peran"
                        allLabel="Semua peran"
                        value={filters.role}
                        onChange={(value) => set('role', value)}
                        options={Object.entries(roleLabels).map(
                            ([value, label]) => ({ value, label }),
                        )}
                    />
                </div>

                {users.length === 0 ? (
                    <EmptyState>
                        {filtered
                            ? 'Tidak ada pengguna yang cocok.'
                            : 'Belum ada pengguna.'}
                    </EmptyState>
                ) : (
                    <DataTable
                        head={[
                            'Nama',
                            'Nama pengguna',
                            'Email',
                            'Peran',
                            'Status',
                            '',
                        ]}
                    >
                        {users.map((user) => (
                            <TableRow key={user.id}>
                                <TableCell className="font-medium">
                                    <Link
                                        href={editUser.url({ userId: user.id })}
                                        className="hover:underline"
                                    >
                                        {user.name}
                                    </Link>
                                </TableCell>
                                <TableCell className="font-mono text-xs">
                                    {user.username ?? '—'}
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {user.email ?? '—'}
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap gap-1">
                                        {user.roleLabels.length === 0 ? (
                                            <Badge variant="outline">
                                                Tanpa peran
                                            </Badge>
                                        ) : (
                                            user.roleLabels.map((label) => (
                                                <Badge
                                                    key={label}
                                                    variant="secondary"
                                                >
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
                                        <Link
                                            href={editUser.url({
                                                userId: user.id,
                                            })}
                                        >
                                            Ubah
                                        </Link>
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                )}

                <ListPager
                    url={url}
                    filters={initial}
                    pagination={pagination}
                />
            </div>
        </TenantShell>
    );
}
