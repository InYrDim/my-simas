import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { email } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/PasswordController';
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

/** Ask for a link to set a new password. The answer never says whether the email has an account. */
export default function ForgotPassword() {
    const form = useForm({ email: '' });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(email.url(), {
            onSuccess: () => form.reset('email'),
        });
    }

    return (
        <LembarShell
            area="PPDB · Calon siswa"
            sheet="Lembar lupa kata sandi"
            title="Lupa kata sandi"
            lead="Masukkan email akun PPDB Anda. Kami mengirim tautan untuk membuat kata sandi baru."
            progress={filledShare([form.data.email])}
            instructions={[
                'Tautan berlaku 60 menit dan hanya bisa dipakai sekali.',
                'Tidak ada email masuk? Periksa folder spam, lalu kirim lagi.',
            ]}
            after={
                <LembarLink href={login.url()} className="text-(--graphite)">
                    Kembali ke halaman masuk
                </LembarLink>
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

                <LembarButton
                    type="submit"
                    disabled={form.processing}
                    className="w-full"
                >
                    {form.processing ? 'Mengirim...' : 'Kirim tautan'}
                </LembarButton>
            </form>
        </LembarShell>
    );
}
