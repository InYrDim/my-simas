import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/RegisterController';
import { login } from '@/routes/ppdb/account';
import {
    LembarButton,
    LembarInput,
    LembarLink,
} from '@shared/components/lembar/fields';
import {
    filledShare,
    LembarShell,
} from '@shared/components/lembar/LembarShell';

/**
 * Create the applicant's own account. Joining a school comes after, inside
 * the account. The hidden "website" field is a honeypot: people never see
 * it; bots that fill it are silently dropped.
 */
export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        website: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(store.url(), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <LembarShell
            area="PPDB · Calon siswa"
            sheet="Lembar akun PPDB"
            title="Buat akun PPDB"
            lead="Buat akun untuk mendaftar ke sekolah. Setelah itu Anda bergabung ke sekolah dengan kode dari sekolah."
            progress={filledShare([
                form.data.name,
                form.data.email,
                form.data.password,
                form.data.password_confirmation,
            ])}
            instructions={[
                'Buat akun dan buka tautan verifikasi di email.',
                'Bergabung ke sekolah dengan kode atau tautan dari sekolah.',
                'Isi formulir pendaftaran, lalu pantau statusnya di halaman akun.',
            ]}
            after={
                <span>
                    Sudah punya akun?{' '}
                    <LembarLink
                        href={login.url()}
                        className="text-(--graphite)"
                    >
                        Masuk
                    </LembarLink>
                </span>
            }
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <LembarInput
                    label="Nama Anda"
                    id="name"
                    name="name"
                    autoComplete="name"
                    autoFocus
                    required
                    value={form.data.name}
                    error={form.errors.name}
                    hint="Nama Anda atau nama orang tua/wali yang mendaftarkan."
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />

                <LembarInput
                    label="Email"
                    id="email"
                    name="email"
                    type="email"
                    autoComplete="username"
                    required
                    value={form.data.email}
                    error={form.errors.email}
                    hint="Kami mengirim tautan verifikasi ke email ini."
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                />

                <LembarInput
                    label="Kata sandi"
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

                <LembarInput
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

                {/* Honeypot: hidden from people and from the tab order. */}
                <div className="hidden" aria-hidden="true">
                    <label>
                        Website
                        <input
                            type="text"
                            name="website"
                            tabIndex={-1}
                            autoComplete="off"
                            value={form.data.website}
                            onChange={(event) =>
                                form.setData('website', event.target.value)
                            }
                        />
                    </label>
                </div>

                <LembarButton
                    type="submit"
                    disabled={form.processing}
                    className="w-full"
                >
                    {form.processing ? 'Membuat akun...' : 'Buat akun'}
                </LembarButton>
            </form>
        </LembarShell>
    );
}
