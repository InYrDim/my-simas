import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/ForgotPasswordController';
import { create as login } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/SessionController';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import ApplicantShell from '../../../Components/ApplicantShell';

/**
 * Applicant "forgot password": the answer is the same for every email, so
 * the form says nothing about which addresses are registered.
 */
export default function ForgotPassword() {
    const form = useForm({ email: '' });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(store.url(), { preserveScroll: true });
    }

    return (
        <ApplicantShell
            title="Lupa kata sandi"
            description="Masukkan email akun pendaftaran sekolah Anda. Kami mengirim petunjuk untuk membuat kata sandi baru."
            footer={
                <Link
                    href={login.url()}
                    className="text-primary hover:underline"
                >
                    Kembali ke halaman masuk
                </Link>
            }
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    <Field data-invalid={!!form.errors.email}>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="username"
                            autoFocus
                            required
                            value={form.data.email}
                            aria-invalid={!!form.errors.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Mengirim...' : 'Kirim petunjuk'}
                    </Button>
                </FieldGroup>
            </form>
        </ApplicantShell>
    );
}
