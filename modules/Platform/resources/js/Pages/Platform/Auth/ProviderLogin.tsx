import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';
import { AuthShell } from '@shared/components/AuthShell';

import { store as providerLoginStore } from '@/actions/Modules/Platform/App/Http/Controllers/Auth/ProviderAuthenticatedSessionController';

/**
 * Provider console login (SaaS staff). Central hosts only — the page
 * lives on the /platform prefix of the central domain, never on a
 * school subdomain.
 */
export default function ProviderLogin() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(providerLoginStore.url(), {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <AuthShell
            tone="dark"
            title="Console Provider"
            subtitle="Adminstrasi SaaS: tenant, modul, dan izin."
            footer="SIMAS provider console"
        >
            <form onSubmit={submit} className="flex flex-col gap-4" noValidate>
                <AuthInput
                    label="Email"
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="username"
                    autoFocus
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
                    autoComplete="current-password"
                    required
                    value={form.data.password}
                    error={form.errors.password}
                    onChange={(event) =>
                        form.setData('password', event.target.value)
                    }
                />

                <label className="flex items-center gap-2 text-sm text-zinc-400">
                    <input
                        type="checkbox"
                        name="remember"
                        checked={form.data.remember}
                        onChange={(event) =>
                            form.setData('remember', event.target.checked)
                        }
                        className="size-4 rounded border-zinc-700 bg-zinc-950 text-emerald-600 focus:ring-emerald-600/20"
                    />
                    Ingat saya
                </label>

                <button
                    type="submit"
                    disabled={form.processing}
                    className="mt-2 w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-500 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {form.processing ? 'Memeriksa...' : 'Masuk'}
                </button>
            </form>
        </AuthShell>
    );
}
