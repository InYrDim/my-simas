import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import TenantShell from '@shared/components/TenantShell';
import { Button } from '@shared/components/ui/button';
import { Card, CardContent } from '@shared/components/ui/card';
import { FieldGroup } from '@shared/components/ui/field';

import { store as usersStore } from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

import RoleSelectField from '../../../Components/RoleSelectField';
import TextField from '../../../Components/TextField';

interface CreateProps {
    roleLabels: Record<string, string>;
}

/**
 * Direct-create a user (school admin): name, email, password, optional
 * role. Email is marked verified server-side — trusted admin input.
 */
export default function UsersCreate({ roleLabels }: CreateProps) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        role: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(usersStore.url(), {
            onFinish: () => form.reset('password'),
        });
    }

    return (
        <TenantShell width="max-w-3xl">
            <Head title="Tambah Pengguna" />

            <Link
                href="/users"
                className="mb-6 inline-block text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                Kembali
            </Link>

            <h1 className="text-xl font-semibold">Tambah Pengguna</h1>

            <p className="mt-1 text-sm text-muted-foreground">
                Akun langsung aktif dan terverifikasi — kata sandi diserahkan
                kepada Anda untuk diteruskan.
            </p>

            <Card className="mt-8">
                <CardContent>
                    <form onSubmit={submit} noValidate>
                        <FieldGroup>
                            <TextField
                                label="Nama lengkap"
                                id="name"
                                type="text"
                                autoComplete="name"
                                autoFocus
                                required
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />

                            <TextField
                                label="Email"
                                id="email"
                                type="email"
                                autoComplete="email"
                                required
                                value={form.data.email}
                                error={form.errors.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                            />

                            <TextField
                                label="Kata sandi"
                                id="password"
                                type="password"
                                autoComplete="new-password"
                                required
                                hint="Minimal 8 karakter."
                                value={form.data.password}
                                error={form.errors.password}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                            />

                            <RoleSelectField
                                roleLabels={roleLabels}
                                value={form.data.role}
                                error={form.errors.role}
                                onChange={(role) => form.setData('role', role)}
                            />

                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Menyimpan...' : 'Simpan'}
                            </Button>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
        </TenantShell>
    );
}
