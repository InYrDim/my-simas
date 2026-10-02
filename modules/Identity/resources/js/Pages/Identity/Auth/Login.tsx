import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';
import { AuthShell } from '@shared/components/AuthShell';
import { useTenant } from '@shared/hooks/useTenant';

import { store as loginStore } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { create as forgotPassword } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/PasswordResetLinkController';
import { create as registerSchool } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/RegisterController';

/**
 * Tenant login (school portal). The school is identified by the school
 * code typed here (tenant id today, NPSN later) and remembered in the
 * session server-side; there is no per-school host. An account signs in
 * with its email, or with its username when it has none (NIS, NIP).
 */
export default function Login() {
    const tenant = useTenant();

    const form = useForm({
        school: '',
        login: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(loginStore.url(), {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <AuthShell
            tone="light"
            eyebrow={tenant ? tenant.name : 'Portal Sekolah'}
            title="Masuk ke akun Anda"
            subtitle="Gunakan email atau nomor induk dan kata sandi akun sekolah Anda."
            footer="SIMAS untuk sekolah"
        >
            <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                <AuthInput
                    label="Kode sekolah"
                    id="school"
                    name="school"
                    type="text"
                    autoComplete="organization"
                    autoFocus
                    required
                    value={form.data.school}
                    error={form.errors.school}
                    onChange={(event) =>
                        form.setData('school', event.target.value)
                    }
                />

                <AuthInput
                    label="Email atau NIS/NIP"
                    id="login"
                    name="login"
                    type="text"
                    autoComplete="username"
                    required
                    value={form.data.login}
                    error={form.errors.login}
                    onChange={(event) =>
                        form.setData('login', event.target.value)
                    }
                />

                <AuthInput
                    label="Kata sandi"
                    id="password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    required
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                />

                <div className="flex items-center justify-between">
                    <label className="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={form.data.remember}
                            onChange={(event) =>
                                form.setData('remember', event.target.checked)
                            }
                            className="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-600/20"
                        />
                        Ingat saya
                    </label>

                    <a
                        href={forgotPassword.url()}
                        className="text-sm text-emerald-700 hover:text-emerald-800 hover:underline"
                    >
                        Lupa kata sandi?
                    </a>
                </div>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="mt-2 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {form.processing ? 'Memeriksa...' : 'Masuk'}
                </button>

                <p className="text-center text-sm text-zinc-600 dark:text-zinc-400">
                    Sekolah Anda belum terdaftar?{' '}
                    <a
                        href={registerSchool.url()}
                        className="text-emerald-700 hover:text-emerald-800 hover:underline"
                    >
                        Daftarkan sekolah
                    </a>
                </p>
            </form>
        </AuthShell>
    );
}
