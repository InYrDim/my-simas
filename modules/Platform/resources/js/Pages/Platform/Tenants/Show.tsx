import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import {
    index as invoicesIndex,
    voidMethod as voidInvoice,
} from '@/actions/Modules/Platform/App/Http/Controllers/InvoiceController';
import {
    activate as activateTenant,
    index as tenantsIndex,
    suspend as suspendTenant,
    syncModules,
    update as updateTenant,
    updateCode as updateTenantCode,
} from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';
import {
    activate as activateSubscription,
    cancel as cancelSubscription,
    changeCycle,
    changePlan,
    extendTrial,
    renew as renewSubscription,
    startTrial,
} from '@/actions/Modules/Platform/App/Http/Controllers/TenantSubscriptionController';
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
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@shared/components/ui/tabs';
import { TableCell, TableRow } from '@shared/components/ui/table';
import { Badge } from '@shared/components/ui/badge';

import { consolePath } from '../../../Components/consolePath';
import {
    DataTable,
    DefinitionList,
    EmptyState,
    OptionSelect,
    PageHeader,
    Panel,
    StatusChip,
} from '../../../Components/ConsoleParts';
import {
    cycleLabel,
    formatDate,
    formatRupiah,
    relativeDue,
} from '../../../Components/format';
import ProviderLayout from '../../../Components/ProviderLayout';
import { send } from '../../../Components/send';
import type {
    BillingCycle,
    ConsoleInvoice,
    ConsoleModule,
    ConsolePlan,
    ConsoleRole,
    ConsoleSubscription,
    PermissionGroup,
    TenantDetail,
    UsageLine,
} from '../../../types/console';

interface ShowProps {
    tenant: TenantDetail;
    subscription: ConsoleSubscription | null;
    plans: ConsolePlan[];
    usage: UsageLine[];
    modules: ConsoleModule[];
    permissionCatalog: PermissionGroup[];
    roles: ConsoleRole[];
    invoices: ConsoleInvoice[];
    trialDays: number;
}

type Route = { tenant: string };

/**
 * Tenant detail: profile, suspend/activate, module flags, the roles and
 * permissions the school holds, and its subscription with invoices.
 */
export default function TenantShow({
    tenant,
    subscription,
    plans,
    usage,
    modules,
    permissionCatalog,
    roles,
    invoices,
    trialDays,
}: ShowProps) {
    const route = { tenant: tenant.id };

    return (
        <ProviderLayout>
            <Head title={tenant.name} />

            <Button asChild variant="link" className="px-0">
                <Link href={consolePath(tenantsIndex.url())}>
                    Kembali ke daftar tenant
                </Link>
            </Button>

            <PageHeader
                title={tenant.name}
                description={`Kode sekolah ${tenant.slug}`}
                actions={<StatusChip status={tenant.status} />}
            />

            <Tabs defaultValue="ringkasan" className="mt-8">
                <TabsList>
                    <TabsTrigger value="ringkasan">Ringkasan</TabsTrigger>
                    <TabsTrigger value="modul">Modul</TabsTrigger>
                    <TabsTrigger value="izin">Peran & izin</TabsTrigger>
                    <TabsTrigger value="langganan">Langganan</TabsTrigger>
                    <TabsTrigger value="pengaturan">Pengaturan</TabsTrigger>
                </TabsList>

                <TabsContent value="ringkasan" className="mt-6">
                    <Overview
                        tenant={tenant}
                        subscription={subscription}
                        usage={usage}
                        route={route}
                    />
                </TabsContent>
                <TabsContent value="modul" className="mt-6">
                    <ModulesTab modules={modules} route={route} />
                </TabsContent>
                <TabsContent value="izin" className="mt-6">
                    <PermissionsTab catalog={permissionCatalog} roles={roles} />
                </TabsContent>
                <TabsContent value="langganan" className="mt-6">
                    <SubscriptionTab
                        subscription={subscription}
                        plans={plans}
                        invoices={invoices}
                        trialDays={trialDays}
                        route={route}
                    />
                </TabsContent>
                <TabsContent value="pengaturan" className="mt-6">
                    <SettingsTab tenant={tenant} route={route} />
                </TabsContent>
            </Tabs>
        </ProviderLayout>
    );
}

function SettingsTab({
    tenant,
    route,
}: {
    tenant: TenantDetail;
    route: Route;
}) {
    const form = useForm({ code: tenant.slug });

    return (
        <Panel title="Kode sekolah">
            <p className="mb-4 text-sm text-muted-foreground">
                Kode yang diketik pengguna saat masuk, dan yang dipakai pada
                tautan masuk, tautan PPDB, dan email sekolah ini.
            </p>
            <Field data-invalid={!!form.errors.code} className="max-w-sm">
                <FieldLabel htmlFor="tenant-code">Kode sekolah</FieldLabel>
                <Input
                    id="tenant-code"
                    value={form.data.code}
                    onChange={(event) =>
                        form.setData('code', event.target.value)
                    }
                    className="font-mono"
                    aria-invalid={!!form.errors.code}
                />
                <FieldDescription>
                    Huruf kecil, angka, dan tanda hubung. Alamat masuk:{' '}
                    <span className="font-mono">
                        /{form.data.code || 'kode'}/login
                    </span>
                </FieldDescription>
                <FieldError>{form.errors.code}</FieldError>
            </Field>
            <div className="mt-4">
                <Confirm
                    trigger="Simpan kode sekolah"
                    title="Ubah kode sekolah?"
                    description="Kode lama langsung tidak berlaku. Tautan masuk, tautan PPDB, dan QR yang sudah dibagikan dengan kode lama berhenti bekerja, dan pengguna harus memakai kode baru."
                    action="Ya, ubah kode"
                    onConfirm={() =>
                        form.put(consolePath(updateTenantCode.url(route)), {
                            preserveScroll: true,
                        })
                    }
                />
            </div>
        </Panel>
    );
}

function Confirm({
    trigger,
    title,
    description,
    action,
    onConfirm,
}: {
    trigger: string;
    title: string;
    description: string;
    action: string;
    onConfirm: () => void;
}) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button variant="outline">{trigger}</Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Batal</AlertDialogCancel>
                    <AlertDialogAction
                        variant="destructive"
                        onClick={onConfirm}
                    >
                        {action}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function Overview({
    tenant,
    subscription,
    usage,
    route,
}: {
    tenant: TenantDetail;
    subscription: ConsoleSubscription | null;
    usage: UsageLine[];
    route: Route;
}) {
    const form = useForm({
        name: tenant.name,
        timezone: tenant.timezone,
        domain: tenant.domain ?? '',
        billing_email: tenant.billingEmail ?? '',
        billing_name: tenant.billingName ?? '',
    });
    const suspended = tenant.status === 'suspended';

    function submit(event: FormEvent) {
        event.preventDefault();
        form.put(consolePath(updateTenant.url(route)), {
            preserveScroll: true,
        });
    }

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Panel title="Data sekolah" className="lg:col-span-2">
                <form onSubmit={submit} noValidate>
                    <FieldGroup className="sm:grid sm:grid-cols-3">
                        <Field data-invalid={!!form.errors.name}>
                            <FieldLabel htmlFor="tenant-name">
                                Nama sekolah
                            </FieldLabel>
                            <Input
                                id="tenant-name"
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                aria-invalid={!!form.errors.name}
                                required
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>
                        <Field data-invalid={!!form.errors.timezone}>
                            <FieldLabel htmlFor="tenant-tz">
                                Zona waktu
                            </FieldLabel>
                            <Input
                                id="tenant-tz"
                                value={form.data.timezone}
                                onChange={(event) =>
                                    form.setData('timezone', event.target.value)
                                }
                                placeholder="Asia/Jakarta"
                                aria-invalid={!!form.errors.timezone}
                                required
                            />
                            <FieldError>{form.errors.timezone}</FieldError>
                        </Field>
                        <Field data-invalid={!!form.errors.domain}>
                            <FieldLabel htmlFor="tenant-domain">
                                Domain khusus (opsional)
                            </FieldLabel>
                            <Input
                                id="tenant-domain"
                                value={form.data.domain}
                                onChange={(event) =>
                                    form.setData('domain', event.target.value)
                                }
                                className="font-mono"
                                placeholder="sekolah.sch.id"
                                aria-invalid={!!form.errors.domain}
                            />
                            <FieldError>{form.errors.domain}</FieldError>
                        </Field>
                        <Field data-invalid={!!form.errors.billing_email}>
                            <FieldLabel htmlFor="tenant-billing-email">
                                Email tagihan
                            </FieldLabel>
                            <Input
                                id="tenant-billing-email"
                                type="email"
                                value={form.data.billing_email}
                                onChange={(event) =>
                                    form.setData(
                                        'billing_email',
                                        event.target.value,
                                    )
                                }
                                placeholder="keuangan@sekolah.sch.id"
                                aria-invalid={!!form.errors.billing_email}
                            />
                            <FieldError>{form.errors.billing_email}</FieldError>
                        </Field>
                        <Field data-invalid={!!form.errors.billing_name}>
                            <FieldLabel htmlFor="tenant-billing-name">
                                Nama kontak tagihan
                            </FieldLabel>
                            <Input
                                id="tenant-billing-name"
                                value={form.data.billing_name}
                                onChange={(event) =>
                                    form.setData(
                                        'billing_name',
                                        event.target.value,
                                    )
                                }
                                aria-invalid={!!form.errors.billing_name}
                            />
                            <FieldError>{form.errors.billing_name}</FieldError>
                        </Field>
                    </FieldGroup>
                    <Button
                        type="submit"
                        className="mt-4"
                        disabled={form.processing || !form.isDirty}
                    >
                        Simpan data
                    </Button>
                </form>
            </Panel>

            <Panel title="Status tenant">
                <DefinitionList
                    rows={[
                        [
                            'Status',
                            <StatusChip key="s" status={tenant.status} />,
                        ],
                        [
                            'Terdaftar',
                            tenant.createdAt
                                ? formatDate(tenant.createdAt)
                                : '—',
                        ],
                    ]}
                />
                <div className="mt-5">
                    {suspended ? (
                        <Button
                            onClick={() =>
                                send('post', activateTenant.url(route))
                            }
                        >
                            Aktifkan kembali
                        </Button>
                    ) : (
                        <Confirm
                            trigger="Tangguhkan"
                            title={`Tangguhkan ${tenant.name}?`}
                            description="Seluruh pengguna sekolah tidak bisa masuk sampai tenant diaktifkan kembali."
                            action="Ya, tangguhkan"
                            onConfirm={() =>
                                send('post', suspendTenant.url(route))
                            }
                        />
                    )}
                </div>
            </Panel>

            <Panel title="Langganan saat ini">
                {subscription === null ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada langganan. Mulai uji coba dari tab Langganan.
                    </p>
                ) : (
                    <DefinitionList
                        rows={[
                            ['Paket', subscription.planName ?? '—'],
                            ['Siklus', cycleLabel[subscription.cycle]],
                            [
                                'Status',
                                <StatusChip
                                    key="sub"
                                    status={subscription.state}
                                />,
                            ],
                            [
                                subscription.status === 'trial'
                                    ? 'Uji coba sampai'
                                    : 'Berakhir',
                                subscription.endsAt
                                    ? `${formatDate(subscription.endsAt)} (${relativeDue(subscription.endsAt)})`
                                    : '—',
                            ],
                        ]}
                    />
                )}
            </Panel>

            <Panel title="Pemakaian" className="lg:col-span-2">
                {usage.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada pemakaian yang diukur.
                    </p>
                ) : (
                    <DefinitionList
                        rows={usage.map((line): [string, ReactNode] => [
                            line.label,
                            <span
                                key={line.key}
                                className="flex flex-wrap items-center gap-2"
                            >
                                {line.used.toLocaleString('id-ID')}{' '}
                                {line.limit === null
                                    ? `${line.unit} (tanpa batas)`
                                    : `dari ${line.limit.toLocaleString('id-ID')} ${line.unit}`}
                                {line.state !== 'ok' && (
                                    <StatusChip status={line.state} />
                                )}
                            </span>,
                        ])}
                    />
                )}
            </Panel>

            <Panel title="Admin sekolah" className="lg:col-span-2">
                <p className="text-sm text-muted-foreground">
                    Akun admin sekolah dikelola di halaman Pengguna.
                </p>
                <Button asChild variant="outline" className="mt-4">
                    <Link href={`/users?tenant=${tenant.id}`}>
                        Lihat admin sekolah ini
                    </Link>
                </Button>
            </Panel>
        </div>
    );
}

function ModulesTab({
    modules,
    route,
}: {
    modules: ConsoleModule[];
    route: Route;
}) {
    const [enabled, setEnabled] = useState<string[]>(
        modules.filter((module) => module.enabled).map((module) => module.key),
    );

    return (
        <Panel title="Modul yang diaktifkan">
            <FieldGroup>
                {modules.map((module) => (
                    <Field key={module.key} orientation="horizontal">
                        <Checkbox
                            id={`module-${module.key}`}
                            checked={
                                module.locked || enabled.includes(module.key)
                            }
                            disabled={module.locked}
                            onCheckedChange={(checked) =>
                                setEnabled((current) =>
                                    checked === true
                                        ? [...current, module.key]
                                        : current.filter(
                                              (key) => key !== module.key,
                                          ),
                                )
                            }
                        />
                        <div className="flex flex-col gap-1">
                            <FieldLabel htmlFor={`module-${module.key}`}>
                                {module.label}
                            </FieldLabel>
                            <FieldDescription>
                                {module.alwaysActive
                                    ? 'Selalu aktif'
                                    : module.locked
                                      ? 'Wajib untuk login sekolah'
                                      : module.inPlan
                                        ? 'Termasuk dalam paket'
                                        : 'Di luar paket'}
                            </FieldDescription>
                        </div>
                    </Field>
                ))}
            </FieldGroup>
            <Button
                className="mt-5"
                onClick={() =>
                    send('put', syncModules.url(route), { modules: enabled })
                }
            >
                Simpan modul
            </Button>
        </Panel>
    );
}

function PermissionsTab({
    catalog,
    roles,
}: {
    catalog: PermissionGroup[];
    roles: ConsoleRole[];
}) {
    if (catalog.length === 0) {
        return <EmptyState>Belum ada izin terdaftar.</EmptyState>;
    }

    return (
        <div className="flex flex-col gap-6">
            <p className="text-sm text-muted-foreground">
                Peran sekolah dan izin yang melekat padanya. Hanya baca; admin
                sekolah mengelola peran di tenantnya sendiri.
            </p>

            {catalog.map((group) => (
                <div key={group.key}>
                    <h2 className="mb-3 text-sm font-semibold">
                        {group.label}
                    </h2>
                    <DataTable
                        head={['Izin', ...roles.map((role) => role.label)]}
                    >
                        {group.permissions.map((permission) => (
                            <TableRow key={permission}>
                                <TableCell className="font-mono text-xs text-muted-foreground">
                                    {permission}
                                </TableCell>
                                {roles.map((role) => (
                                    <TableCell key={role.key}>
                                        {role.permissions.includes(
                                            permission,
                                        ) ? (
                                            <Badge>ya</Badge>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                tidak
                                            </span>
                                        )}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))}
                    </DataTable>
                </div>
            ))}
        </div>
    );
}

function SubscriptionTab({
    subscription,
    plans,
    invoices,
    trialDays,
    route,
}: {
    subscription: ConsoleSubscription | null;
    plans: ConsolePlan[];
    invoices: ConsoleInvoice[];
    trialDays: number;
    route: Route;
}) {
    const [planKey, setPlanKey] = useState(
        subscription?.planKey ?? plans[0]?.key ?? '',
    );
    const [cycle, setCycle] = useState<BillingCycle>(
        subscription?.cycle ?? 'monthly',
    );
    const [days, setDays] = useState(trialDays);

    const plan = plans.find((candidate) => candidate.key === planKey);
    const isTrial = subscription?.status === 'trial';
    const isCancelled = subscription?.status === 'cancelled';

    const planSelect = (
        <OptionSelect
            label="Paket"
            value={planKey}
            onChange={setPlanKey}
            options={plans.map((candidate) => ({
                value: candidate.key,
                label: `${candidate.name} — ${formatRupiah(candidate.priceMonthly)}/bln`,
            }))}
        />
    );

    const cycleSelect = (
        <OptionSelect
            label="Siklus"
            value={cycle}
            onChange={(value) => setCycle(value as BillingCycle)}
            options={[
                { value: 'monthly', label: 'Bulanan' },
                { value: 'yearly', label: 'Tahunan' },
            ]}
        />
    );

    if (subscription === null) {
        return (
            <Panel title="Mulai uji coba">
                {plans.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada paket aktif. Buat paket di menu Langganan →
                        Paket.
                    </p>
                ) : (
                    <>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Tenant ini belum punya langganan.
                        </p>
                        <div className="grid gap-3 sm:grid-cols-[1fr_8rem_auto]">
                            {planSelect}
                            <Input
                                type="number"
                                min={1}
                                max={90}
                                value={days}
                                onChange={(event) =>
                                    setDays(Number(event.target.value))
                                }
                                aria-label="Lama uji coba (hari)"
                            />
                            <Button
                                onClick={() =>
                                    send('post', startTrial.url(route), {
                                        plan: planKey,
                                        days,
                                    })
                                }
                            >
                                Mulai uji coba
                            </Button>
                        </div>
                    </>
                )}
            </Panel>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <Panel title="Langganan">
                <DefinitionList
                    rows={[
                        [
                            'Mode',
                            isTrial
                                ? 'Uji coba'
                                : isCancelled
                                  ? 'Berhenti'
                                  : 'Berlangganan',
                        ],
                        ['Paket', subscription.planName ?? '—'],
                        ['Siklus', cycleLabel[subscription.cycle]],
                        [
                            'Tarif',
                            subscription.amount !== null
                                ? `${formatRupiah(subscription.amount)} / ${subscription.cycle === 'yearly' ? 'tahun' : 'bulan'}`
                                : '—',
                        ],
                        [
                            'Status',
                            <StatusChip key="st" status={subscription.state} />,
                        ],
                        isTrial
                            ? [
                                  'Uji coba sampai',
                                  subscription.trialEndsAt
                                      ? formatDate(subscription.trialEndsAt)
                                      : '—',
                              ]
                            : [
                                  'Periode',
                                  subscription.periodStart &&
                                  subscription.periodEnd
                                      ? `${formatDate(subscription.periodStart)} – ${formatDate(subscription.periodEnd)}`
                                      : '—',
                              ],
                    ]}
                />
            </Panel>

            {isTrial && (
                <Panel title="Uji coba">
                    <div className="grid gap-3 sm:grid-cols-[8rem_auto]">
                        <Input
                            type="number"
                            min={1}
                            max={90}
                            value={days}
                            onChange={(event) =>
                                setDays(Number(event.target.value))
                            }
                            aria-label="Tambah hari uji coba"
                        />
                        <Button
                            variant="outline"
                            onClick={() =>
                                send('post', extendTrial.url(route), { days })
                            }
                        >
                            Perpanjang uji coba
                        </Button>
                    </div>
                </Panel>
            )}

            <Panel
                title={
                    isTrial || isCancelled
                        ? 'Aktifkan langganan'
                        : 'Ubah langganan'
                }
            >
                <div className="grid gap-3 sm:grid-cols-2">
                    {planSelect}
                    {cycleSelect}
                </div>
                {plan !== undefined && (
                    <p className="mt-3 text-xs text-muted-foreground">
                        Tarif {cycle === 'yearly' ? 'tahunan' : 'bulanan'}:{' '}
                        {formatRupiah(
                            cycle === 'yearly'
                                ? plan.priceYearly
                                : plan.priceMonthly,
                        )}
                    </p>
                )}
                <div className="mt-4 flex flex-wrap gap-3">
                    <Button
                        onClick={() =>
                            send('post', activateSubscription.url(route), {
                                plan: planKey,
                                cycle,
                            })
                        }
                    >
                        Terbitkan tagihan
                    </Button>
                    {!isTrial && !isCancelled && (
                        <>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    send('put', changePlan.url(route), {
                                        plan: planKey,
                                    })
                                }
                            >
                                Ganti paket saja
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    send('put', changeCycle.url(route), {
                                        cycle,
                                    })
                                }
                            >
                                Ubah siklus saja
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    send('post', renewSubscription.url(route))
                                }
                            >
                                Terbitkan tagihan perpanjangan
                            </Button>
                        </>
                    )}
                </div>
                <p className="mt-3 text-xs text-muted-foreground">
                    Langganan menjadi aktif setelah tagihan ditandai lunas.
                    Ganti paket berlaku untuk modul sekarang dan tarif pada
                    tagihan berikutnya.
                </p>
            </Panel>

            {!isCancelled && (
                <Panel title="Hentikan langganan">
                    <Confirm
                        trigger="Hentikan langganan"
                        title="Hentikan langganan ini?"
                        description="Tenant tidak otomatis ditangguhkan."
                        action="Ya, hentikan"
                        onConfirm={() =>
                            send('post', cancelSubscription.url(route))
                        }
                    />
                </Panel>
            )}

            <div>
                <h2 className="mb-3 text-sm font-semibold">Riwayat tagihan</h2>
                {invoices.length === 0 ? (
                    <EmptyState>Belum ada tagihan untuk tenant ini.</EmptyState>
                ) : (
                    <DataTable
                        head={[
                            'Nomor',
                            'Terbit',
                            'Jatuh tempo',
                            'Jumlah',
                            'Status',
                            '',
                        ]}
                    >
                        {invoices.map((invoice) => (
                            <TableRow key={invoice.id}>
                                <TableCell className="font-mono text-xs">
                                    {invoice.number}
                                </TableCell>
                                <TableCell>
                                    {formatDate(invoice.issuedAt)}
                                </TableCell>
                                <TableCell>
                                    {formatDate(invoice.dueAt)}
                                </TableCell>
                                <TableCell>
                                    {formatRupiah(invoice.amount)}
                                </TableCell>
                                <TableCell>
                                    <StatusChip status={invoice.state} />
                                </TableCell>
                                <TableCell>
                                    {invoice.status === 'unpaid' && (
                                        <div className="flex gap-2">
                                            <Button asChild size="sm">
                                                <Link
                                                    href={consolePath(
                                                        invoicesIndex.url({
                                                            query: {
                                                                q: invoice.number,
                                                            },
                                                        }),
                                                    )}
                                                >
                                                    Konfirmasi pembayaran
                                                </Link>
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    send(
                                                        'post',
                                                        voidInvoice.url({
                                                            invoice: invoice.id,
                                                        }),
                                                    )
                                                }
                                            >
                                                Batalkan
                                            </Button>
                                        </div>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                )}
            </div>
        </div>
    );
}
