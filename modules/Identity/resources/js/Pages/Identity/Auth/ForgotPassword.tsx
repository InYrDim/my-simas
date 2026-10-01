import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthInput } from '@shared/components/AuthInput';
import { AuthShell } from '@shared/components/AuthShell';
import { useTenant } from '@shared/hooks/useTenant';

import { store as requestReset } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/PasswordResetLinkController';

/**
 * "Forgot password" (school portal). Always answers with the same
 * generic confirmation — the server never discloses whether an email
 * exists, so this page only ever shows the neutral status message.
 */
export default function ForgotPassword() {
    const tenant = useTenant();

    const form = useForm({ school: '', email: '' });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(requestReset.url());
    }

    return (
        <AuthShell
            tone="light"
            eyebrow={tenant ? tenant.name : 'Portal Sekolah'}
            title="Lupa kata sandi"
            subtitle="Masukkan email akun Anda — jika terdaftar, kami kirim tautan pengaturan ulang."
            footer="SIMAS untuk sekolah"
        >
            {form.wasSuccessful ? (
                <p className="text-sm text-emerald-700">
                    Jika email terdaftar, tautan reset telah dikirim. Periksa
                    kotak masuk Anda.
                </p>
            ) : (
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-4"
                    noValidate
                >
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

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="mt-2 w-full rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-emerald-800 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {form.processing ? 'Mengirim...' : 'Kirim tautan reset'}
                    </button>
                </form>
            )}
        </AuthShell>
    );
}
