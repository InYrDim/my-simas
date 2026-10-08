import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { LembarButton, LembarInput } from '@shared/components/lembar/fields';
import {
    filledShare,
    LembarShell,
} from '@shared/components/lembar/LembarShell';
import { useTenant } from '@shared/hooks/useTenant';

import { store as setPasswordStore } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/SetPasswordController';

type SetPasswordProps = {
    email: string;
    token: string;
};

/**
 * "Set password" acceptance (school portal): the emailed link a newly
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
        <LembarShell
            area="Portal sekolah"
            sheet="Lembar aktivasi akun"
            sheetNote={tenant?.name}
            title="Aktivasi akun Anda"
            lead="Buat kata sandi untuk mengaktifkan akun sekolah Anda."
            progress={filledShare([
                form.data.email,
                form.data.password,
                form.data.password_confirmation,
            ])}
            instructions={[
                'Tautan aktivasi berlaku 60 menit. Jika sudah lewat, minta tautan baru kepada pengirim undangan.',
                'Setelah akun aktif, Anda langsung masuk ke sekolah Anda.',
            ]}
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <LembarInput
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

                <LembarInput
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

                <LembarButton
                    type="submit"
                    disabled={form.processing}
                    className="w-full"
                >
                    {form.processing ? 'Mengaktifkan...' : 'Aktifkan akun'}
                </LembarButton>
            </form>
        </LembarShell>
    );
}
