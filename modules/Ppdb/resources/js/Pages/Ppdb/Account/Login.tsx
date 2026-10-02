import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { request } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/PasswordController';
import { store } from '@/actions/Modules/Ppdb/App/Http/Controllers/Account/SessionController';
import { register } from '@/routes/ppdb/account';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Field, FieldError, FieldGroup, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import PortalPage from '../../../Components/PortalPage';

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
        <PortalPage
            title="Masuk ke akun PPDB"
            description="Lanjutkan pendaftaran Anda atau lihat statusnya."
            footer={
                <span>
                    Belum punya akun?{' '}
                    <Link href={register.url()} className="text-primary hover:underline">
                        Buat akun
                    </Link>
                </span>
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
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>

                    <Field data-invalid={!!form.errors.password}>
                        <FieldLabel htmlFor="password">Kata sandi</FieldLabel>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="current-password"
                            required
                            value={form.data.password}
                            aria-invalid={!!form.errors.password}
                            onChange={(event) => form.setData('password', event.target.value)}
                        />
                        <FieldError>{form.errors.password}</FieldError>
                    </Field>

                    <Link href={request.url()} className="text-sm text-primary hover:underline">
                        Lupa kata sandi?
                    </Link>

                    <Field orientation="horizontal">
                        <Checkbox
                            id="remember"
                            checked={form.data.remember}
                            onCheckedChange={(checked) => form.setData('remember', checked === true)}
                        />
                        <FieldLabel htmlFor="remember">Ingat saya</FieldLabel>
                    </Field>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Memeriksa...' : 'Masuk'}
                    </Button>
                </FieldGroup>
            </form>
        </PortalPage>
    );
}
