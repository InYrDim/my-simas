import { Head, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

import { store as providerLoginStore } from '@/actions/Modules/Platform/App/Http/Controllers/Auth/ProviderAuthenticatedSessionController';

import { consolePath } from '../../../Components/consolePath';
import { inputClass } from '../../../Components/ui';

/**
 * Provider console login (SaaS staff). Served on the console host
 * (console.localhost/login), never on a school's login path. Uses the
 * console theme tokens, not the shared auth shell (which is the school
 * portal's paper/green look).
 */
export default function ProviderLogin() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(consolePath(providerLoginStore.url()), {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <div className="console-theme flex min-h-[100dvh] items-center justify-center bg-background px-6 py-10">
            <Head title="Masuk" />

            <div className="console-card w-full max-w-sm border border-border bg-card p-8">
                <h1 className="text-xl font-semibold text-foreground">
                    Console Provider
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Administrasi SaaS: tenant, modul, dan izin.
                </p>

                <form onSubmit={submit} className="mt-8 flex flex-col gap-4" noValidate>
                    <Field label="Email" id="email" error={form.errors.email}>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="username"
                            autoFocus
                            required
                            value={form.data.email}
                            aria-invalid={form.errors.email ? true : undefined}
                            aria-describedby={form.errors.email ? 'email-error' : undefined}
                            onChange={(event) => form.setData('email', event.target.value)}
                            className={inputClass}
                        />
                    </Field>

                    <Field label="Kata sandi" id="password" error={form.errors.password}>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="current-password"
                            required
                            value={form.data.password}
                            aria-invalid={form.errors.password ? true : undefined}
                            aria-describedby={form.errors.password ? 'password-error' : undefined}
                            onChange={(event) => form.setData('password', event.target.value)}
                            className={inputClass}
                        />
                    </Field>

                    <label className="flex min-h-11 items-center gap-3 text-sm text-muted-foreground">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={form.data.remember}
                            onChange={(event) => form.setData('remember', event.target.checked)}
                            className="size-5 accent-primary"
                        />
                        Ingat saya
                    </label>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="inline-flex min-h-11 w-full items-center justify-center bg-primary px-4 text-sm font-semibold text-white transition-colors hover:bg-primary/90 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {form.processing ? 'Memeriksa...' : 'Masuk'}
                    </button>
                </form>
            </div>
        </div>
    );
}

function Field({
    label,
    id,
    error,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="flex flex-col gap-2">
            <label htmlFor={id} className="text-sm font-medium text-foreground">
                {label}
            </label>
            {children}
            {error && (
                <p id={`${id}-error`} className="text-xs text-destructive">
                    {error}
                </p>
            )}
        </div>
    );
}
