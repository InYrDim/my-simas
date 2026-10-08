import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { request } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/PasswordController';
import { store } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/SessionController';
import { register } from '@/routes/ppdb/account';
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

/** Sign in to the applicant's own account. */
export default function Login() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(store.url(), {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <LembarShell
            area="PPDB · Calon siswa"
            sheet="Lembar masuk PPDB"
            title="Masuk ke akun PPDB"
            lead="Lanjutkan pendaftaran Anda atau lihat statusnya."
            progress={filledShare([form.data.email, form.data.password])}
            instructions={[
                'Akun PPDB terpisah dari akun sekolah. Siswa dan guru masuk lewat halaman masuk sekolah.',
                'Satu akun untuk mendaftar ke satu sekolah, dengan kode atau tautan dari sekolah itu.',
                'Hasil seleksi tampil di halaman akun Anda setelah sekolah mengumumkannya.',
            ]}
            after={
                <span>
                    Belum punya akun?{' '}
                    <LembarLink
                        href={register.url()}
                        className="text-(--graphite)"
                    >
                        Buat akun
                    </LembarLink>
                </span>
            }
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <LembarInput
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

                    <LembarLink href={request.url()}>
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
