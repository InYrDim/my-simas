import { usePage, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import {
    LembarButton,
    LembarInput,
    LembarLink,
    LembarNotice,
} from '@shared/components/lembar/fields';
import {
    filledShare,
    LembarShell,
} from '@shared/components/lembar/LembarShell';
import { useTenant } from '@shared/hooks/useTenant';

import { create as login } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { store as requestReset } from '@/actions/Modules/Identity/App/Http/Controllers/Auth/PasswordResetLinkController';

/**
 * "Forgot password" (school portal). Always answers with the same
 * generic confirmation — the server never discloses whether an email
 * exists, so this page only ever shows the neutral status message.
 */
export default function ForgotPassword() {
    const tenant = useTenant();
    const { flash } = usePage<{ flash?: { status?: string | null } }>().props;

    const form = useForm({ school: '', email: '' });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(requestReset.url());
    }

    return (
        <LembarShell
            area="Portal sekolah"
            sheet="Lembar lupa kata sandi"
            sheetNote={tenant?.name}
            title="Lupa kata sandi"
            lead="Masukkan email akun Anda — jika terdaftar, kami kirim tautan pengaturan ulang."
            progress={
                form.wasSuccessful
                    ? 1
                    : filledShare([form.data.school, form.data.email])
            }
            instructions={[
                'Tautan berlaku 60 menit dan hanya bisa dipakai sekali.',
                'Akun yang masuk dengan NIS atau NIP tidak punya email: minta admin sekolah membantu mengatur ulang kata sandi.',
            ]}
            after={
                <LembarLink href={login.url()} className="text-(--graphite)">
                    Kembali ke halaman masuk
                </LembarLink>
            }
        >
            {form.wasSuccessful ? (
                !flash?.status && (
                    <LembarNotice>
                        Jika email terdaftar, tautan reset telah dikirim.
                        Periksa kotak masuk Anda.
                    </LembarNotice>
                )
            ) : (
                <form
                    onSubmit={submit}
                    className="flex flex-col gap-5"
                    noValidate
                >
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

                    <LembarButton
                        type="submit"
                        disabled={form.processing}
                        className="w-full"
                    >
                        {form.processing ? 'Mengirim...' : 'Kirim tautan reset'}
                    </LembarButton>
                </form>
            )}
        </LembarShell>
    );
}
