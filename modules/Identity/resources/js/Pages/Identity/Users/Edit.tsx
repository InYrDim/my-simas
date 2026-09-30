import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';

import {
    deactivate as deactivateUser,
    reactivate as reactivateUser,
    sendReset as sendResetLink,
    update as usersUpdate,
} from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

interface EditProps {
    user: {
        id: number;
        name: string;
        email: string;
        roleNames: string[];
        isActive: boolean;
        hasPassword: boolean;
    };
    roleLabels: Record<string, string>;
    isSelf: boolean;
    isLastActiveAdmin: boolean;
}

/**
 * Edit a user (school admin): profile name, role assign/remove via
 * dropdown (full sync), deactivate/reactivate, and the reset-link
 * sender. Anti-lockout: the deactivate button is disabled for self and
 * the tenant's last active admin — the server refuses them regardless.
 */
export default function UsersEdit({
    user,
    roleLabels,
    isSelf,
    isLastActiveAdmin,
}: EditProps) {
    const form = useForm({
        name: user.name,
        roles: user.roleNames,
    });

    const deactivateDisabled = isSelf || isLastActiveAdmin;

    const deactivateHint = isSelf
        ? 'Anda tidak dapat menonaktifkan akun sendiri.'
        : isLastActiveAdmin
          ? 'Admin aktif terakhir tidak dapat dinonaktifkan.'
          : undefined;

    function submit(event: FormEvent) {
        event.preventDefault();

        form.put(usersUpdate.url({ userId: user.id }));
    }

    return (
        <div className="min-h-[100dvh] bg-zinc-50">
            <header className="border-b border-zinc-200 bg-white">
                <div className="mx-auto flex h-16 max-w-3xl items-center justify-between px-6">
                    <span className="text-sm font-semibold text-zinc-900">
                        Pengguna
                    </span>

                    <Link
                        href="/users"
                        className="text-sm text-zinc-500 transition-colors hover:text-zinc-800"
                    >
                        Kembali
                    </Link>
                </div>
            </header>

            <Head title={`Edit — ${user.name}`} />

            <main className="mx-auto max-w-3xl px-6 py-10">
                <h1 className="text-xl font-semibold text-zinc-900">
                    {user.name}
                </h1>

                <p className="mt-1 text-sm text-zinc-500">{user.email}</p>

                {!user.isActive && (
                    <div className="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        Akun ini sedang dinonaktifkan — tidak dapat masuk.
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="mt-8 flex flex-col gap-5 rounded-lg border border-zinc-200 bg-white p-6"
                    noValidate
                >
                    <AuthInput
                        label="Nama lengkap"
                        id="name"
                        name="name"
                        type="text"
                        autoComplete="name"
                        required
                        value={form.data.name}
                        error={form.errors.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />

                    <div className="flex flex-col gap-2">
                        <label
                            htmlFor="roles"
                            className="text-sm font-medium text-zinc-700 dark:text-zinc-300"
                        >
                            Peran
                        </label>

                        <select
                            id="roles"
                            name="roles"
                            multiple
                            value={form.data.roles}
                            onChange={(event) => {
                                const selected = Array.from(
                                    event.target.selectedOptions,
                                    (option) => option.value,
                                );

                                form.setData('roles', selected);
                            }}
                            className="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:bg-zinc-950 dark:text-zinc-100"
                            size={Math.min(
                                Object.keys(roleLabels).length || 1,
                                5,
                            )}
                        >
                            {Object.entries(roleLabels).map(([name, label]) => (
                                <option key={name} value={name}>
                                    {label}
                                </option>
                            ))}
                        </select>

                        <p className="text-xs text-zinc-500">
                            Tahan Ctrl/CMD untuk memilih lebih dari satu.
                        </p>
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan perubahan'}
                    </button>
                </form>

                <section className="mt-8 flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-6">
                    <h2 className="text-sm font-semibold text-zinc-900">
                        Aksi akun
                    </h2>

                    {user.isActive ? (
                        <button
                            type="button"
                            disabled={deactivateDisabled}
                            title={deactivateHint}
                            onClick={() =>
                                router.patch(
                                    deactivateUser.url({ userId: user.id }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="w-full rounded-lg border border-red-300 px-4 py-2.5 text-sm font-semibold text-red-700 transition-colors hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Nonaktifkan akun
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={() =>
                                router.patch(
                                    reactivateUser.url({ userId: user.id }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="w-full rounded-lg border border-emerald-300 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-50"
                        >
                            Aktifkan kembali
                        </button>
                    )}

                    {user.hasPassword && (
                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    sendResetLink.url({ userId: user.id }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                            className="w-full rounded-lg border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition-colors hover:bg-zinc-50"
                        >
                            Kirim tautan reset kata sandi
                        </button>
                    )}

                    {!user.hasPassword && (
                        <p className="text-xs text-zinc-500">
                            Akun ini belum mengaktifkan kata sandi (undangan
                            belum diterima). Tautan reset tidak berlaku untuk
                            akun tanpa kata sandi.
                        </p>
                    )}
                </section>
            </main>
        </div>
    );
}
