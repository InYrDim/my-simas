import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import {
    LembarButton,
    LembarCheck,
    LembarInput,
    LembarLink,
} from '@shared/components/lembar/fields';
import {
    filledShare,
    LembarShell,
} from '@shared/components/lembar/LembarShell';
import { useTenant } from '@shared/hooks/useTenant';

import { store as loginStore } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { create as forgotPassword } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/PasswordResetLinkController';
import { create as registerSchool } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/RegisterController';

/**
 * Tenant login (school portal). The school is identified by the school
 * code typed here (tenant id today, NPSN later) or carried by the school's
 * own address `/{code}/login` (then the field is hidden), and remembered
 * in the session server-side; there is no per-school host. An account signs in
 * with its email, or with its username when it has none (NIS, NIP).
 */
export default function Login({
    schoolLinked = false,
}: {
    schoolLinked?: boolean;
}) {
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

    const answers = schoolLinked
        ? [form.data.login, form.data.password]
        : [form.data.school, form.data.login, form.data.password];

    return (
        <LembarShell
            area="Portal sekolah"
            sheet="Lembar masuk sekolah"
            sheetNote={tenant?.name}
            title="Masuk ke akun Anda"
            lead="Gunakan email atau nomor induk dan kata sandi akun sekolah Anda."
            progress={filledShare(answers)}
            instructions={[
                schoolLinked
                    ? 'Kode sekolah sudah terisi dari alamat sekolah yang Anda buka.'
                    : 'Kode sekolah didapat dari admin sekolah Anda.',
                'Akun tanpa email masuk dengan NIS (siswa) atau NIP (guru).',
                'Di HP atau komputer bersama, jangan centang “Ingat saya”.',
            ]}
            after={
                <span>
                    Sekolah Anda belum terdaftar?{' '}
                    <LembarLink
                        href={registerSchool.url()}
                        className="text-(--graphite)"
                    >
                        Daftarkan sekolah
                    </LembarLink>
                </span>
            }
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                {!schoolLinked && (
                    <LembarInput
                        label="Kode sekolah"
                        id="school"
                        name="school"
                        comb
                        autoComplete="organization"
                        autoFocus
                        required
                        value={form.data.school}
                        error={form.errors.school}
                        onChange={(event) =>
                            form.setData('school', event.target.value)
                        }
                    />
                )}

                <LembarInput
                    label="Email atau NIS/NIP"
                    id="login"
                    name="login"
                    autoComplete="username"
                    autoCapitalize="none"
                    autoFocus={schoolLinked}
                    required
                    value={form.data.login}
                    error={form.errors.login}
                    onChange={(event) =>
                        form.setData('login', event.target.value)
                    }
                />

                <LembarInput
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

                <div className="flex flex-wrap items-center justify-between gap-x-4">
                    <LembarCheck
                        label="Ingat saya"
                        name="remember"
                        checked={form.data.remember}
                        onChange={(checked) =>
                            form.setData('remember', checked)
                        }
                    />

                    <LembarLink href={forgotPassword.url()}>
                        Lupa kata sandi?
                    </LembarLink>
                </div>

                <LembarButton
                    type="submit"
                    disabled={form.processing}
                    className="w-full"
                >
                    {form.processing ? 'Memeriksa...' : 'Masuk'}
                </LembarButton>
            </form>
        </LembarShell>
    );
}
