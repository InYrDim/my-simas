import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store as providerLoginStore } from '@/actions/Modules/Platform/App/Http/Controllers/Auth/ProviderAuthenticatedSessionController';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';

import { consolePath } from '../../../Components/consolePath';

/**
 * Provider console login (SaaS staff). Served on the console host
 * (console.localhost/login), never on a school's login path. Built from
 * the shared shadcn primitives (Card, Field, Input, Checkbox, Button).
 */
export default function ProviderLogin() {
    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(consolePath(providerLoginStore.url()), {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <div className="flex min-h-[100dvh] items-center justify-center bg-background px-6 py-10 text-foreground">
            <Head title="Masuk" />

            <Card className="w-full max-w-sm">
                <CardHeader>
                    <CardTitle className="text-xl">Console Provider</CardTitle>
                    <CardDescription>
                        Administrasi SaaS: tenant, modul, dan izin.
                    </CardDescription>
                </CardHeader>

                <CardContent>
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
                                    onChange={(event) =>
                                        form.setData('password', event.target.value)
                                    }
                                />
                                <FieldError>{form.errors.password}</FieldError>
                            </Field>

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
                </CardContent>

                <CardFooter>
                    <p className="text-xs text-muted-foreground">
                        SIMAS provider console
                    </p>
                </CardFooter>
            </Card>
        </div>
    );
}
