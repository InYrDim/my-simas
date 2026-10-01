import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { create as forgotPassword } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/ForgotPasswordController';
import { create as register } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/RegisterController';
import { store } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/SessionController';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import ApplicantShell from '../../../Components/ApplicantShell';

/** Applicant login: the account used to register a school. */
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
        <ApplicantShell
            title="Masuk sebagai pemohon"
            description="Lanjutkan pendaftaran sekolah Anda atau lihat status pengajuannya."
            footer={
                <span>
                    Belum punya akun?{' '}
                    <Link href={register.url()} className="text-primary hover:underline">
                        Daftarkan sekolah
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

                    <Link
                        href={forgotPassword.url()}
                        className="text-sm text-primary hover:underline"
                    >
                        Lupa kata sandi?
                    </Link>

                    <Field orientation="horizontal">
                        <Checkbox
                            id="remember"
                            checked={form.data.remember}
                            onCheckedChange={(checked) =>
                                form.setData('remember', checked === true)
                            }
                        />
                        <FieldLabel htmlFor="remember">Ingat saya</FieldLabel>
                    </Field>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Memeriksa...' : 'Masuk'}
                    </Button>
                </FieldGroup>
            </form>
        </ApplicantShell>
    );
}
