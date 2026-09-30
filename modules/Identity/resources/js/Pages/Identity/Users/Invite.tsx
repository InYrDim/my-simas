import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';

import { storeInvite as inviteStore } from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

interface InviteProps {
    roleLabels: Record<string, string>;
}

/**
 * Invite a user by email (school admin): the account is created (or
 * re-invited) WITHOUT a password and activates via the emailed
 * set-password link. Re-inviting an existing invited user replaces
 * their old link — this is the deliberate "resend" path (no separate
 * button, locked decision).
 */
export default function UsersInvite({ roleLabels }: InviteProps) {
    const form = useForm({
        name: '',
        email: '',
        role: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(inviteStore.url(), {
            onSuccess: () => form.reset(),
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

            <Head title="Undang Pengguna" />

            <main className="mx-auto max-w-3xl px-6 py-10">
                <h1 className="text-xl font-semibold text-zinc-900">
                    Undang via Email
                </h1>

                <p className="mt-1 text-sm text-zinc-500">
                    Akun dibuat tanpa kata sandi — penerima menyelesaikan
                    aktivasi lewat tautan di email (berlaku 60 menit).
                    Mengundang ulang email yang sama membuat tautan baru.
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
                        {form.processing ? 'Mengirim...' : 'Kirim undangan'}
                    </button>
                </form>
            </main>
        </div>
    );
}
