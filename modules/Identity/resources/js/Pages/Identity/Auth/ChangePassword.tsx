import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import {
    LembarButton,
    LembarInput,
    LembarLink,
} from '@shared/components/lembar/fields';
import {
    filledShare,
    LembarShell,
} from '@shared/components/lembar/LembarShell';
import { useTenant } from '@shared/hooks/useTenant';

import { destroy as logout } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { update as changePassword } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/ChangePasswordController';

type ChangePasswordProps = {
    /** The account must set its own password before it can go on. */
    forced: boolean;
};

/**
 * Change your own password. An account that was given its password (a
 * student's birth date, a password read out by the admin) lands here
 * after signing in and stays until it has chosen a new one.
 */
export default function ChangePassword({ forced }: ChangePasswordProps) {
    const tenant = useTenant();

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.put(changePassword.url(), {
            onError: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <LembarShell
            area="Portal sekolah"
            sheet="Lembar ganti kata sandi"
            sheetNote={tenant?.name}
            title="Ganti kata sandi"
            lead={
                forced
                    ? 'Kata sandi Anda masih kata sandi awal. Buat kata sandi sendiri untuk melanjutkan.'
                    : 'Buat kata sandi baru untuk akun Anda.'
            }
            progress={filledShare([
                form.data.current_password,
                form.data.password,
                form.data.password_confirmation,
            ])}
            instructions={[
                forced
                    ? 'Kata sandi sekarang adalah kata sandi awal yang Anda terima dari sekolah.'
                    : 'Kata sandi sekarang adalah kata sandi yang Anda pakai untuk masuk tadi.',
                'Kata sandi baru hanya Anda yang tahu. Jangan pakai tanggal lahir.',
            ]}
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
                <LembarInput
                    label="Kata sandi sekarang"
                    id="current_password"
                    name="current_password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    required
                    value={form.data.current_password}
                    error={form.errors.current_password}
                    onChange={(event) =>
                        form.setData('current_password', event.target.value)
                    }
                />

                <LembarInput
                    label="Kata sandi baru"
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

                <div className="flex items-center justify-between gap-4">
                    {forced ? (
                        <span />
                    ) : (
                        <LembarLink href="/beranda">Kembali</LembarLink>
                    )}
                    <LembarLink
                        href={logout.url()}
                        method="post"
                        as="button"
                        className="font-medium text-(--pencil) decoration-(--ink-line) decoration-1 hover:text-(--graphite)"
                    >
                        Keluar
                    </LembarLink>
                </div>
            </form>
        </LembarShell>
    );
}
