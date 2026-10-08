import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { LembarButton, LembarInput } from '@shared/components/lembar/fields';
import {
    filledShare,
    LembarShell,
} from '@shared/components/lembar/LembarShell';
import { useTenant } from '@shared/hooks/useTenant';

import { store as resetStore } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/NewPasswordController';

type ResetPasswordProps = {
    email: string;
    token: string;
};

/**
 * "Reset password" (school portal): consumes a tenant-scoped token from
 * the email link and sets the new password. On success the user is
 * logged in server-side and redirected home.
 */
export default function ResetPassword({ email, token }: ResetPasswordProps) {
    const tenant = useTenant();

    const form = useForm({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(resetStore.url());
    }

    return (
        <LembarShell
            area="Portal sekolah"
            sheet="Lembar kata sandi baru"
            sheetNote={tenant?.name}
            title="Atur kata sandi baru"
            lead="Buat kata sandi baru untuk akun sekolah Anda."
            progress={filledShare([
                form.data.email,
                form.data.password,
                form.data.password_confirmation,
            ])}
            instructions={[
                'Tautan dari email berlaku 60 menit dan hanya bisa dipakai sekali.',
                'Setelah disimpan, Anda langsung masuk dengan kata sandi baru.',
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
                    label="Kata sandi baru"
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
                    label="Ulangi kata sandi baru"
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
                    {form.processing ? 'Menyimpan...' : 'Simpan kata sandi'}
                </LembarButton>
            </form>
        </LembarShell>
    );
}
