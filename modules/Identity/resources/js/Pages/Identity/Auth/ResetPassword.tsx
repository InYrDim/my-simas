import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';
import { AuthShell } from '@shared/components/AuthShell';
import { useTenant } from '@shared/hooks/useTenant';

import { store as resetStore } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/NewPasswordController';

type ResetPasswordProps = {
    email: string;
    token: string;
};

/**
 * "Reset password" (school portal): consumes a tenant-scoped token from
 * the email link and sets the new password. On success the user is
 * logged in server-side and redirected home.
 */
export default function ResetPassword({ email, token }: ResetPasswordProps) {
    const tenant = useTenant();

    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(resetStore.url());
    }

    return (
        <AuthShell
            tone="light"
            eyebrow={tenant ? tenant.name : 'Portal Sekolah'}
            title="Atur kata sandi baru"
            subtitle="Buat kata sandi baru untuk akun sekolah Anda."
            footer="SIMAS untuk sekolah"
        >
            <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                <AuthInput
                    label="Email"
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="username"
                    required
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                />

                <AuthInput
                    label="Kata sandi baru"
                    id="password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    autoFocus
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
            </form>
        </AuthShell>
    );
}
