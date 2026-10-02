import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';
import { AuthShell } from '@shared/components/AuthShell';
import { useTenant } from '@shared/hooks/useTenant';

import { destroy as logout } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { update as changePassword } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/ChangePasswordController';

type ChangePasswordProps = {
    /** The account must set its own password before it can go on. */
    forced: boolean;
};

/**
 * Change your own password. An account that was given its password (a
 * student's birth date, a password read out by the admin) lands here
 * after signing in and stays until it has chosen a new one.
 */
export default function ChangePassword({ forced }: ChangePasswordProps) {
    const tenant = useTenant();

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.put(changePassword.url(), {
            onError: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthShell
            tone="light"
            eyebrow={tenant ? tenant.name : 'Portal Sekolah'}
            title="Ganti kata sandi"
            subtitle={
                forced
                    ? 'Kata sandi Anda masih kata sandi awal. Buat kata sandi sendiri untuk melanjutkan.'
                    : 'Buat kata sandi baru untuk akun Anda.'
            }
            footer="SIMAS untuk sekolah"
        >
            <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                <AuthInput
                    label="Kata sandi sekarang"
                    id="current_password"
                    name="current_password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    required
                    value={form.data.current_password}
                    error={form.errors.current_password}
                    onChange={(event) =>
                        form.setData('current_password', event.target.value)
                    }
                />

                <AuthInput
                    label="Kata sandi baru"
                    id="password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                />

                <AuthInput
                    label="Ulangi kata sandi baru"
                    id="password_confirmation"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    required
                    value={form.data.password_confirmation}
                    error={form.errors.password_confirmation}
                    onChange={(event) =>
                        form.setData(
                            'password_confirmation',
                            event.target.value,
                        )
                    }
                />

                <button
                    type="submit"
                    disabled={form.processing}
                    className="mt-2 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {form.processing ? 'Menyimpan...' : 'Simpan kata sandi'}
                </button>

                <div className="flex items-center justify-between text-sm">
                    {forced ? (
                        <span />
                    ) : (
                        <Link
                            href="/beranda"
                            className="text-emerald-700 hover:text-emerald-800 hover:underline"
                        >
                            Kembali
                        </Link>
                    )}
                    <Link
                        href={logout.url()}
                        method="post"
                        as="button"
                        className="text-zinc-600 hover:text-zinc-900 hover:underline"
                    >
                        Keluar
                    </Link>
                </div>
            </form>
        </AuthShell>
    );
}
