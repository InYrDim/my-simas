import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import TenantShell from '@shared/components/TenantShell';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Button } from '@shared/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@shared/components/ui/card';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@shared/components/ui/field';

import {
    deactivate as deactivateUser,
    reactivate as reactivateUser,
    sendReset as sendResetLink,
    update as usersUpdate,
} from '@/actions/Modules/Identity/App/Http/Controllers/UsersManagementController';

import TextField from '../../../Components/TextField';

interface EditProps {
    user: {
        id: number;
        name: string;
        email: string;
        roleNames: string[];
        isActive: boolean;
        hasPassword: boolean;
    };
    roleLabels: Record<string, string>;
    isSelf: boolean;
    isLastActiveAdmin: boolean;
}

/**
 * Edit a user (school admin): profile name, role assign/remove via
 * checkboxes (full sync), deactivate/reactivate, and the reset-link
 * sender. Anti-lockout: the deactivate button is disabled for self and
 * the tenant's last active admin — the server refuses them regardless.
 */
export default function UsersEdit({
    user,
    roleLabels,
    isSelf,
    isLastActiveAdmin,
}: EditProps) {
    const form = useForm({
        name: user.name,
        roles: user.roleNames,
    });

    const deactivateDisabled = isSelf || isLastActiveAdmin;

    const deactivateHint = isSelf
        ? 'Anda tidak dapat menonaktifkan akun sendiri.'
        : isLastActiveAdmin
          ? 'Admin aktif terakhir tidak dapat dinonaktifkan.'
          : undefined;

    function submit(event: FormEvent) {
        event.preventDefault();

        form.put(usersUpdate.url({ userId: user.id }));
    }

    function toggleRole(name: string, checked: boolean) {
        form.setData(
            'roles',
            checked
                ? [...form.data.roles, name]
                : form.data.roles.filter((role) => role !== name),
        );
    }

    return (
        <TenantShell width="max-w-3xl">
            <Head title={`Edit — ${user.name}`} />

            <Link
                href="/users"
                className="mb-6 inline-block text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                Kembali
            </Link>

            <h1 className="text-xl font-semibold">{user.name}</h1>

            <p className="mt-1 text-sm text-muted-foreground">{user.email}</p>

            {!user.isActive && (
                <Alert variant="destructive" className="mt-6">
                    <AlertDescription>
                        Akun ini sedang dinonaktifkan — tidak dapat masuk.
                    </AlertDescription>
                </Alert>
            )}

            <Card className="mt-8">
                <CardContent>
                    <form onSubmit={submit} noValidate>
                        <FieldGroup>
                            <TextField
                                label="Nama lengkap"
                                id="name"
                                type="text"
                                autoComplete="name"
                                required
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />

                            <FieldSet>
                                <FieldLegend variant="label">Peran</FieldLegend>

                                {Object.entries(roleLabels).map(
                                    ([name, label]) => (
                                        <Field
                                            key={name}
                                            orientation="horizontal"
                                        >
                                            <Checkbox
                                                id={`role-${name}`}
                                                checked={form.data.roles.includes(
                                                    name,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggleRole(
                                                        name,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <FieldLabel
                                                htmlFor={`role-${name}`}
                                                className="font-normal"
                                            >
                                                {label}
                                            </FieldLabel>
                                        </Field>
                                    ),
                                )}

                                <FieldDescription>
                                    Pilih satu atau lebih peran.
                                </FieldDescription>
                            </FieldSet>

                            <Button type="submit" disabled={form.processing}>
                                {form.processing
                                    ? 'Menyimpan...'
                                    : 'Simpan perubahan'}
                            </Button>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>

            <Card className="mt-8">
                <CardHeader>
                    <CardTitle>Aksi akun</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {user.isActive ? (
                        <Button
                            type="button"
                            variant="destructive"
                            disabled={deactivateDisabled}
                            title={deactivateHint}
                            onClick={() =>
                                router.patch(
                                    deactivateUser.url({ userId: user.id }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Nonaktifkan akun
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.patch(
                                    reactivateUser.url({ userId: user.id }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Aktifkan kembali
                        </Button>
                    )}

                    {user.hasPassword ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    sendResetLink.url({ userId: user.id }),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Kirim tautan reset kata sandi
                        </Button>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            Akun ini belum mengaktifkan kata sandi (undangan
                            belum diterima). Tautan reset tidak berlaku untuk
                            akun tanpa kata sandi.
                        </p>
                    )}
                </CardContent>
            </Card>
        </TenantShell>
    );
}
