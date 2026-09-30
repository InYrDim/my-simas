import { Head, Link } from '@inertiajs/react';

import {
    create as usersCreate,
    edit as editUser,
    invite as usersInvite,
} from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

import type { ManagedUser } from '@/types/ManagedUser';

interface IndexProps {
    users: ManagedUser[];
    status?: string;
}

/**
 * User list (school admin): name, email, roles, status. Entry point to
 * create / edit / deactivate / reactivate / send-reset.
 */
export default function UsersIndex({ users, status }: IndexProps) {
    return (
        <div className="min-h-[100dvh] bg-zinc-50">
            <header className="border-b border-zinc-200 bg-white">
                <div className="mx-auto flex h-16 max-w-5xl items-center justify-between px-6">
                    <span className="text-sm font-semibold text-zinc-900">
                        Pengguna
                    </span>

                    <div className="flex items-center gap-3">
                        <Link
                            href={usersInvite.url()}
                            className="rounded-lg border border-emerald-700 px-4 py-2 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-50"
                        >
                            Undang
                        </Link>

                        <Link
                            href={usersCreate.url()}
                            className="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-800"
                        >
                            Tambah Pengguna
                        </Link>
                    </div>
                </div>
            </header>

            <Head title="Pengguna" />

            <main className="mx-auto max-w-5xl px-6 py-10">
                <h1 className="text-xl font-semibold text-zinc-900">
                    Daftar Pengguna
                </h1>

                <p className="mt-1 text-sm text-zinc-500">
                    Kelola akun staf sekolah: profil, peran, dan status aktif.
                </p>

                {status !== undefined && status !== '' && (
                    <div className="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {status}
                    </div>
                )}

                {users.length === 0 ? (
                    <div className="mt-10 rounded-lg border border-zinc-200 bg-white px-6 py-10 text-center">
                        <p className="text-sm text-zinc-500">
                            Belum ada pengguna selain Anda.
                        </p>
                    </div>
                ) : (
                    <ul className="mt-8 flex flex-col gap-3">
                        {users.map((user) => (
                            <li key={user.id}>
                                <Link
                                    href={editUser.url({ userId: user.id })}
                                    className="block rounded-lg border border-zinc-200 bg-white px-5 py-4 transition-colors hover:border-zinc-300 hover:bg-zinc-50"
                                >
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <p className="font-medium text-zinc-900">
                                                {user.name}
                                            </p>
                                            <p className="mt-0.5 text-sm text-zinc-500">
                                                {user.email}
                                            </p>
                                        </div>

                                        <div className="text-right">
                                            <div className="flex flex-wrap justify-end gap-1">
                                                {user.roleLabels.length ===
                                                0 ? (
                                                    <span className="rounded-full border border-zinc-200 bg-zinc-50 px-2.5 py-0.5 text-xs font-medium text-zinc-500">
                                                        tanpa peran
                                                    </span>
                                                ) : (
                                                    user.roleLabels.map(
                                                        (label) => (
                                                            <span
                                                                key={label}
                                                                className="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700"
                                                            >
                                                                {label}
                                                            </span>
                                                        ),
                                                    )
                                                )}
                                            </div>

                                            <p
                                                className={`mt-1 text-xs font-medium ${
                                                    user.isActive
                                                        ? 'text-zinc-500'
                                                        : 'text-red-600'
                                                }`}
                                            >
                                                {user.isActive
                                                    ? 'aktif'
                                                    : 'nonaktif'}
                                            </p>
                                        </div>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </main>
        </div>
    );
}
