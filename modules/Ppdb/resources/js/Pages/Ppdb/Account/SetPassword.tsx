import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { LembarButton, LembarInput } from '@shared/components/lembar/fields';
import {
    filledShare,
    LembarShell,
} from '@shared/components/lembar/LembarShell';

/**
 * Set a new password from an emailed link. `action` is the signed URL the
 * page was opened with — the form posts back to it, signature included.
 */
export default function SetPassword({
    email,
    action,
}: {
    email: string;
    action: string;
}) {
    const form = useForm({
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(action, {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <LembarShell
            area="PPDB · Calon siswa"
            sheet="Lembar kata sandi baru"
            title="Buat kata sandi baru"
            lead={`Buat kata sandi baru untuk ${email}.`}
            progress={filledShare([
                form.data.password,
                form.data.password_confirmation,
            ])}
        >
            <form onSubmit={submit} className="flex flex-col gap-5" noValidate>
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
                    {form.processing ? 'Menyimpan...' : 'Simpan kata sandi'}
                </LembarButton>
            </form>
        </LembarShell>
    );
}
