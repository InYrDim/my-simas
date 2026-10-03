import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import TenantShell from '@shared/components/TenantShell';
import { Button } from '@shared/components/ui/button';
import { Card, CardContent } from '@shared/components/ui/card';
import { FieldGroup } from '@shared/components/ui/field';

import { storeInvite as inviteStore } from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

import RoleSelectField from '../../../Components/RoleSelectField';
import TextField from '../../../Components/TextField';

interface InviteProps {
    roleLabels: Record<string, string>;
}

/**
 * Invite a user by email (school admin): the account is created (or
 * re-invited) WITHOUT a password and activates via the emailed
 * set-password link. Re-inviting an existing invited user replaces
 * their old link — this is the deliberate "resend" path (no separate
 * button, locked decision).
 */
export default function UsersInvite({ roleLabels }: InviteProps) {
    const form = useForm({
        name: '',
        email: '',
        role: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(inviteStore.url(), {
            onSuccess: () => form.reset(),
        });
    }

    return (
        <TenantShell width="max-w-3xl">
            <Head title="Undang Pengguna" />

            <Link
                href="/users"
                className="mb-6 inline-block text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                Kembali
            </Link>

            <h1 className="text-xl font-semibold">Undang via Email</h1>

            <p className="mt-1 text-sm text-muted-foreground">
                Akun dibuat tanpa kata sandi — penerima menyelesaikan aktivasi
                lewat tautan di email (berlaku 60 menit). Mengundang ulang email
                yang sama membuat tautan baru.
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

                            <RoleSelectField
                                roleLabels={roleLabels}
                                value={form.data.role}
                                error={form.errors.role}
                                onChange={(role) => form.setData('role', role)}
                            />

                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Mengirim...'
                                    : 'Kirim undangan'}
                            </Button>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
        </TenantShell>
    );
}
