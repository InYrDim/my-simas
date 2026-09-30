import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';

import { store as usersStore } from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

interface CreateProps {
    roleLabels: Record<string, string>;
}

/**
 * Direct-create a user (school admin): name, email, password, optional
 * role. Email is marked verified server-side — trusted admin input.
 */
export default function UsersCreate({ roleLabels }: CreateProps) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        role: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(usersStore.url(), {
            onFinish: () => form.reset('password'),
        });
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

            <Head title="Tambah Pengguna" />

            <main className="mx-auto max-w-3xl px-6 py-10">
                <h1 className="text-xl font-semibold text-zinc-900">
                    Tambah Pengguna
                </h1>

                <p className="mt-1 text-sm text-zinc-500">
                    Akun langsung aktif dan terverifikasi — kata sandi
                    diserahkan kepada Anda untuk diteruskan.
                </p>

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
                        autoFocus
                        required
                        value={form.data.name}
                        error={form.errors.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />

                    <AuthInput
                        label="Email"
                        id="email"
                        name="email"
                        type="email"
                        autoComplete="email"
                        required
                        value={form.data.email}
                        error={form.errors.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                    />

                    <AuthInput
                        label="Kata sandi"
                        id="password"
                        name="password"
                        type="password"
                        autoComplete="new-password"
                        required
                        hint="Minimal 8 karakter."
                        value={form.data.password}
                        error={form.errors.password}
                        onChange={(event) =>
                            form.setData('password', event.target.value)
                        }
                    />

                    <div className="flex flex-col gap-2">
                        <label
                            htmlFor="role"
                            className="text-sm font-medium text-zinc-700 dark:text-zinc-300"
                        >
                            Peran (opsional)
                        </label>

                        <select
                            id="role"
                            name="role"
                            value={form.data.role}
                            onChange={(event) =>
                                form.setData('role', event.target.value)
                            }
                            className="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:bg-zinc-950 dark:text-zinc-100"
                        >
                            <option value="">— tanpa peran —</option>

                            {Object.entries(roleLabels).map(([name, label]) => (
                                <option key={name} value={name}>
                                    {label}
                                </option>
                            ))}
                        </select>

                        {form.errors.role !== undefined &&
                            form.errors.role !== '' && (
                                <p className="text-xs text-red-600">
                                    {form.errors.role}
                                </p>
                            )}
                    </div>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="mt-2 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {form.processing ? 'Menyimpan...' : 'Simpan'}
                    </button>
                </form>
            </main>
        </div>
    );
}
