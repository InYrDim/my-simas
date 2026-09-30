import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';
import { AuthShell } from '@shared/components/AuthShell';
import { useTenant } from '@shared/hooks/useTenant';

import { store as setPasswordStore } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/SetPasswordController';

type SetPasswordProps = {
    email: string;
    token: string;
};

/**
 * "Set password" acceptance (tenant host): the emailed link a newly
 * provisioned account (first school admin, invitations) uses to
 * activate — set a password, and the server verifies the email and
 * logs the user in. Same token machinery as reset; the page decides
 * the effect (recorded decision).
 */
export default function SetPassword({ email, token }: SetPasswordProps) {
    const tenant = useTenant();

    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(setPasswordStore.url());
    }

    return (
        <AuthShell
            tone="light"
            eyebrow={tenant ? tenant.name : 'Portal Sekolah'}
            title="Aktivasi akun Anda"
            subtitle="Buat kata sandi untuk mengaktifkan akun sekolah Anda."
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
                    label="Kata sandi"
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
                    label="Ulangi kata sandi"
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
                    {form.processing ? 'Mengaktifkan...' : 'Aktifkan akun'}
                </button>
            </form>
        </AuthShell>
    );
}
