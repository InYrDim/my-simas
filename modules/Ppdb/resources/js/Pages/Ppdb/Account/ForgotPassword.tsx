import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { email } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/PasswordController';
import { login } from '@/routes/ppdb/account';
import { Button } from '@shared/components/ui/button';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import PortalPage from '../../../Components/PortalPage';

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
        <PortalPage
            title="Lupa kata sandi"
            description="Masukkan email akun PPDB Anda. Kami mengirim tautan untuk membuat kata sandi baru."
            footer={
                <Link href={login.url()} className="text-primary hover:underline">
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
                            onChange={(event) => form.setData('email', event.target.value)}
                        />
                        <FieldDescription>Tautan berlaku 60 menit dan hanya bisa dipakai sekali.</FieldDescription>
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Mengirim...' : 'Kirim tautan'}
                    </Button>
                </FieldGroup>
            </form>
        </PortalPage>
    );
}
