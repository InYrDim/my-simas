import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    deactivate as deactivateAdmin,
    index as adminsIndex,
    reactivate as reactivateAdmin,
    resendInvite,
    sendReset,
    store as inviteAdmin,
} from '@/actions/Modules/Identity/App/Http/Controllers/Console/SchoolAdminController';
import { show as showTenant } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';
import {
    consolePath,
} from '@platform/Components/consolePath';
import {
    DataTable,
    EmptyState,
    ListPagination,
    OptionSelect,
    PageHeader,
    Panel,
    StatusChip,
} from '@platform/Components/ConsoleParts';
import ProviderLayout from '@platform/Components/ProviderLayout';
import { applyFilters, send } from '@platform/Components/send';
import type { Paginated } from '@platform/types/console';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@shared/components/ui/alert-dialog';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

interface SchoolAdmin {
    id: number;
    tenantId: string;
    tenantName: string;
    tenantSlug: string;
    tenantStatus: 'active' | 'suspended';
    name: string;
    email: string | null;
    status: 'active' | 'invited' | 'deactivated';
}

interface IndexProps {
    admins: Paginated<SchoolAdmin>;
    filters: { q: string; tenant: string; status: string };
    tenants: { id: string; name: string }[];
}

/**
 * Provider console → Pengguna: the school admins of every tenant. Invite
 * a new admin, resend an invitation, send a password reset link, or
 * deactivate/reactivate an account.
 */
export default function SchoolAdminsIndex({
    admins,
    filters,
    tenants,
}: IndexProps) {
    const [query, setQuery] = useState(filters.q);
    const [adding, setAdding] = useState(false);
    const base = adminsIndex.url();

    const form = useForm({
        tenant_id: filters.tenant || tenants[0]?.id || '',
        name: '',
        email: '',
    });

    function change(next: Partial<typeof filters>) {
        applyFilters(base, { ...filters, q: query, ...next });
    }

    function search(event: FormEvent) {
        event.preventDefault();
        change({});
    }

    function invite(event: FormEvent) {
        event.preventDefault();
        form.post(consolePath(inviteAdmin.url()), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('name', 'email');
                setAdding(false);
            },
        });
    }

    const target = (admin: SchoolAdmin) => ({
        tenant: admin.tenantId,
        userId: admin.id,
    });

    const tenantOptions = tenants.map((tenant) => ({
        value: tenant.id,
        label: tenant.name,
    }));

    return (
        <ProviderLayout>
            <Head title="Pengguna" />

            <PageHeader
                title="Pengguna"
                description="Admin sekolah di semua tenant."
                actions={
                    <Button
                        disabled={tenants.length === 0}
                        onClick={() => setAdding((open) => !open)}
                    >
                        Tambah admin sekolah
                    </Button>
                }
            />

            {adding && (
                <Panel title="Undang admin sekolah" className="mt-6">
                    <form onSubmit={invite} noValidate>
                        <FieldGroup className="sm:grid sm:grid-cols-3">
                            <Field data-invalid={!!form.errors.tenant_id}>
                                <FieldLabel>Sekolah</FieldLabel>
                                <OptionSelect
                                    label="Sekolah"
                                    value={form.data.tenant_id}
                                    onChange={(value) =>
                                        form.setData('tenant_id', value)
                                    }
                                    options={tenantOptions}
                                />
                                <FieldError>{form.errors.tenant_id}</FieldError>
                            </Field>
                            <Field data-invalid={!!form.errors.name}>
                                <FieldLabel htmlFor="admin-name">Nama</FieldLabel>
                                <Input
                                    id="admin-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    aria-invalid={!!form.errors.name}
                                    required
                                />
                                <FieldError>{form.errors.name}</FieldError>
                            </Field>
                            <Field data-invalid={!!form.errors.email}>
                                <FieldLabel htmlFor="admin-email">Email</FieldLabel>
                                <Input
                                    id="admin-email"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) =>
                                        form.setData('email', event.target.value)
                                    }
                                    aria-invalid={!!form.errors.email}
                                    required
                                />
                                <FieldError>{form.errors.email}</FieldError>
                            </Field>
                        </FieldGroup>
                        <div className="mt-4 flex flex-wrap gap-3">
                            <Button type="submit" disabled={form.processing}>
                                Kirim undangan
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setAdding(false)}
                            >
                                Batal
                            </Button>
                        </div>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Admin menerima email berisi tautan untuk membuat
                            kata sandi di alamat sekolahnya.
                        </p>
                    </form>
                </Panel>
            )}

            <form
                onSubmit={search}
                className="mt-8 grid gap-3 sm:grid-cols-[1fr_12rem_11rem_auto]"
            >
                <Input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Cari nama atau email"
                    aria-label="Cari admin"
                />
                <OptionSelect
                    label="Filter sekolah"
                    allLabel="Semua sekolah"
                    value={filters.tenant}
                    onChange={(tenant) => change({ tenant })}
                    options={tenantOptions}
                />
                <OptionSelect
                    label="Filter status"
                    allLabel="Semua status"
                    value={filters.status}
                    onChange={(status) => change({ status })}
                    options={[
                        { value: 'active', label: 'Aktif' },
                        { value: 'invited', label: 'Belum aktivasi' },
                        { value: 'deactivated', label: 'Nonaktif' },
                    ]}
                />
                <Button type="submit" variant="outline">
                    Cari
                </Button>
            </form>

            <div className="mt-6">
                {admins.data.length === 0 ? (
                    <EmptyState>
                        Belum ada admin sekolah yang cocok. Admin pertama dibuat
                        otomatis saat pengajuan disetujui.
                    </EmptyState>
                ) : (
                    <DataTable head={['Admin', 'Sekolah', 'Status', '']}>
                        {admins.data.map((admin) => (
                            <TableRow key={`${admin.tenantId}:${admin.id}`}>
                                <TableCell>
                                    <p className="font-medium">{admin.name}</p>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {admin.email}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <Link
                                        href={consolePath(
                                            showTenant.url({
                                                tenant: admin.tenantId,
                                            }),
                                        )}
                                        className="hover:underline"
                                    >
                                        {admin.tenantName}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    <StatusChip status={admin.status} />
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap gap-2">
                                        {admin.status === 'deactivated' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    send(
                                                        'post',
                                                        reactivateAdmin.url(target(admin)),
                                                    )
                                                }
                                            >
                                                Aktifkan kembali
                                            </Button>
                                        )}
                                        {admin.status === 'invited' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    send(
                                                        'post',
                                                        resendInvite.url(target(admin)),
                                                    )
                                                }
                                            >
                                                Kirim ulang undangan
                                            </Button>
                                        )}
                                        {admin.status === 'active' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    send('post', sendReset.url(target(admin)))
                                                }
                                            >
                                                Kirim tautan reset
                                            </Button>
                                        )}
                                        {admin.status !== 'deactivated' && (
                                            <AlertDialog>
                                                <AlertDialogTrigger asChild>
                                                    <Button size="sm" variant="outline">
                                                        Nonaktifkan
                                                    </Button>
                                                </AlertDialogTrigger>
                                                <AlertDialogContent>
                                                    <AlertDialogHeader>
                                                        <AlertDialogTitle>
                                                            Nonaktifkan {admin.name}?
                                                        </AlertDialogTitle>
                                                        <AlertDialogDescription>
                                                            Akun tidak bisa masuk
                                                            sampai diaktifkan
                                                            kembali. Data dan peran
                                                            tetap tersimpan.
                                                        </AlertDialogDescription>
                                                    </AlertDialogHeader>
                                                    <AlertDialogFooter>
                                                        <AlertDialogCancel>
                                                            Batal
                                                        </AlertDialogCancel>
                                                        <AlertDialogAction
                                                            variant="destructive"
                                                            onClick={() =>
                                                                send(
                                                                    'post',
                                                                    deactivateAdmin.url(target(admin)),
                                                                )
                                                            }
                                                        >
                                                            Ya, nonaktifkan
                                                        </AlertDialogAction>
                                                    </AlertDialogFooter>
                                                </AlertDialogContent>
                                            </AlertDialog>
                                        )}
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                )}
                <ListPagination page={admins} />
            </div>
        </ProviderLayout>
    );
}
