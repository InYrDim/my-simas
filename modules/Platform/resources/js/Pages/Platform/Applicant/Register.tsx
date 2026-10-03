import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/RegisterController';
import { create as login } from '@/actions/Modules/Platform/App/Http/Controllers/Applicant/SessionController';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import ApplicantShell from '../../../Components/ApplicantShell';

/**
 * Step one of registering a school: create the applicant's own account.
 * School data comes after, in onboarding. The hidden "website" field is a
 * honeypot: humans never see it; bots that fill it are silently dropped.
 */
export default function Register() {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        website: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(store.url(), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <ApplicantShell
            title="Daftarkan sekolah Anda"
            step={1}
            description="Buat akun dulu. Setelah itu Anda mengisi data sekolah dan mengajukannya."
            footer={
                <span>
                    Sudah punya akun?{' '}
                    <Link
                        href={login.url()}
                        className="text-primary hover:underline"
                    >
                        Masuk
                    </Link>
                </span>
            }
        >
            <form onSubmit={submit} noValidate>
                <FieldGroup>
                    <Field data-invalid={!!form.errors.name}>
                        <FieldLabel htmlFor="name">Nama Anda</FieldLabel>
                        <Input
                            id="name"
                            name="name"
                            autoComplete="name"
                            autoFocus
                            required
                            value={form.data.name}
                            aria-invalid={!!form.errors.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                        />
                        <FieldError>{form.errors.name}</FieldError>
                    </Field>

                    <Field data-invalid={!!form.errors.email}>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            autoComplete="username"
                            required
                            value={form.data.email}
                            aria-invalid={!!form.errors.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                        />
                        <FieldDescription>
                            Email ini kelak menjadi akun admin sekolah.
                        </FieldDescription>
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>

                    <Field data-invalid={!!form.errors.password}>
                        <FieldLabel htmlFor="password">Kata sandi</FieldLabel>
                        <Input
                            id="password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password}
                            aria-invalid={!!form.errors.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                        />
                        <FieldError>{form.errors.password}</FieldError>
                    </Field>

                    <Field>
                        <FieldLabel htmlFor="password_confirmation">
                            Ulangi kata sandi
                        </FieldLabel>
                        <Input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            required
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>

                    {/* Honeypot: hidden from humans and from the tab order. */}
                    <div className="hidden" aria-hidden="true">
                        <label>
                            Website
                            <input
                                type="text"
                                name="website"
                                tabIndex={-1}
                                autoComplete="off"
                                value={form.data.website}
                                onChange={(event) =>
                                    form.setData('website', event.target.value)
                                }
                            />
                        </label>
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Membuat akun...' : 'Buat akun'}
                    </Button>
                </FieldGroup>
            </form>
        </ApplicantShell>
    );
}
