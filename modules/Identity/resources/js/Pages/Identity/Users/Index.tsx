import { Head, Link } from '@inertiajs/react';

import {
    create as usersCreate,
    edit as editUser,
    invite as usersInvite,
} from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Card, CardContent } from '@shared/components/ui/card';
import TenantShell from '@shared/components/TenantShell';

import type { ManagedUser } from '../../../types/ManagedUser';

interface IndexProps {
    users: ManagedUser[];
}

/**
 * User list (school admin): name, email, roles, status. Entry point to
 * create / edit / deactivate / reactivate / send-reset.
 */
export default function UsersIndex({ users }: IndexProps) {
    return (
        <TenantShell>
            <Head title="Pengguna" />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-xl font-semibold">Daftar Pengguna</h1>

                    <p className="mt-1 text-sm text-muted-foreground">
                        Kelola akun staf sekolah: profil, peran, dan status
                        aktif.
                    </p>
                </div>

                <div className="flex items-center gap-3">
                    <Button asChild variant="outline">
                        <Link href={usersInvite.url()}>Undang</Link>
                    </Button>

                    <Button asChild>
                        <Link href={usersCreate.url()}>Tambah Pengguna</Link>
                    </Button>
                </div>
            </div>

            {users.length === 0 ? (
                <Card className="mt-8">
                    <CardContent className="py-6 text-center text-sm text-muted-foreground">
                        Belum ada pengguna selain Anda.
                    </CardContent>
                </Card>
            ) : (
                <ul className="mt-8 flex flex-col gap-3">
                    {users.map((user) => (
                        <li key={user.id}>
                            <Link
                                href={editUser.url({ userId: user.id })}
                                className="block transition-colors hover:[&>*]:bg-muted/50"
                            >
                                <Card>
                                    <CardContent className="flex items-center justify-between gap-4">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">
                                                {user.name}
                                            </p>
                                            <p className="mt-0.5 truncate text-sm text-muted-foreground">
                                                {user.email}
                                            </p>
                                        </div>

                                        <div className="flex flex-col items-end gap-1">
                                            <div className="flex flex-wrap justify-end gap-1">
                                                {user.roleLabels.length === 0 ? (
                                                    <Badge variant="outline">
                                                        tanpa peran
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

                                            <span
                                                className={`text-xs font-medium ${
                                                    user.isActive
                                                        ? 'text-muted-foreground'
                                                        : 'text-destructive'
                                                }`}
                                            >
                                                {user.isActive
                                                    ? 'aktif'
                                                    : 'nonaktif'}
                                            </span>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </TenantShell>
    );
}
