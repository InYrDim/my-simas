import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { consolePath } from '../../../Components/consolePath';

import { index as tenantsIndex } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';

import ProviderLayout from '../../../Components/ProviderLayout';
import {
    Chip,
    DefinitionList,
    EmptyState,
    PageHeader,
    Panel,
    StatusChip,
    Table,
    buttonGhost,
    buttonStamp,
    buttonVoid,
} from '../../../Components/ui';
import {
    cycleLabel,
    formatDate,
    formatRupiah,
    relativeDue,
} from '../../../Components/format';
import type {
    ConsoleInvoice,
    ConsoleModule,
    ConsolePlan,
    ConsoleRole,
    ConsoleTenant,
    PermissionGroup,
} from '../../../types/console';

interface ShowProps {
    tenant: ConsoleTenant;
    modules: ConsoleModule[];
    permissionCatalog: PermissionGroup[];
    roles: ConsoleRole[];
    plans: ConsolePlan[];
    invoices: ConsoleInvoice[];
}

const tabs = [
    ['ringkasan', 'Ringkasan'],
    ['modul', 'Modul'],
    ['izin', 'Peran & izin'],
    ['langganan', 'Langganan'],
] as const;

type TabKey = (typeof tabs)[number][0];

/**
 * Tenant detail. Every action here is a mock: confirmations render, but
 * nothing is persisted until the backend stage.
 */
export default function TenantShow({
    tenant,
    modules,
    permissionCatalog,
    roles,
    plans,
    invoices,
}: ShowProps) {
    const [tab, setTab] = useState<TabKey>('ringkasan');
    const [notice, setNotice] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);
    const [enabled, setEnabled] = useState<string[]>(tenant.enabledModules);

    const plan = plans.find((candidate) => candidate.key === tenant.plan);
    const suspended = tenant.status === 'suspended';

    return (
        <ProviderLayout>
            <Head title={tenant.name} />

            <Link
                href={consolePath(tenantsIndex.url())}
                className="inline-flex min-h-11 items-center text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                Kembali ke daftar tenant
            </Link>

            <PageHeader
                title={tenant.name}
                description={`/${tenant.slug} · kode sekolah ${tenant.id}`}
                actions={
                    suspended ? (
                        <button
                            type="button"
                            className={buttonStamp}
                            onClick={() => {
                                setNotice('Contoh tampilan: tenant diaktifkan kembali.');
                            }}
                        >
                            Aktifkan kembali
                        </button>
                    ) : (
                        <button
                            type="button"
                            className={buttonGhost}
                            onClick={() => setConfirming(true)}
                        >
                            Tangguhkan
                        </button>
                    )
                }
            />

            {notice !== null && (
                <div
                    role="status"
                    className="mt-6 rounded-lg border border-primary/30 bg-primary/10 px-4 py-3 text-sm text-foreground"
                >
                    {notice} Perubahan belum disimpan (tahap tampilan).
                </div>
            )}

            {confirming && (
                <div
                    role="alertdialog"
                    aria-label="Konfirmasi penangguhan"
                    className="mt-6 rounded-lg border border-destructive/30 bg-destructive/5 p-5"
                >
                    <p className="text-sm text-foreground">
                        Tangguhkan {tenant.name}? Seluruh pengguna sekolah tidak
                        akan bisa masuk sampai tenant diaktifkan kembali.
                    </p>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <button
                            type="button"
                            className={buttonVoid}
                            onClick={() => {
                                setConfirming(false);
                                setNotice('Contoh tampilan: tenant ditangguhkan.');
                            }}
                        >
                            Ya, tangguhkan
                        </button>
                        <button
                            type="button"
                            className={buttonGhost}
                            onClick={() => setConfirming(false)}
                        >
                            Batal
                        </button>
                    </div>
                </div>
            )}

            <div
                role="tablist"
                aria-label="Bagian detail tenant"
                className="mt-8 flex overflow-x-auto border-b border-border"
            >
                {tabs.map(([key, label]) => (
                    <button
                        key={key}
                        type="button"
                        role="tab"
                        aria-selected={tab === key}
                        onClick={() => setTab(key)}
                        className={`min-h-11 px-4 text-sm whitespace-nowrap transition-colors ${
                            tab === key
                                ? 'border-b border-primary font-semibold text-foreground'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <div className="mt-6" role="tabpanel">
                {tab === 'ringkasan' && (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Panel title="Sekolah">
                            <DefinitionList
                                rows={[
                                    ['Status', <StatusChip key="s" status={tenant.status} />],
                                    ['Zona waktu', tenant.timezone],
                                    ['Domain', tenant.domain ?? 'Memakai alamat bawaan'],
                                    ['Terdaftar', formatDate(tenant.createdAt)],
                                    ['Pengguna', tenant.userCount],
                                ]}
                            />
                        </Panel>
                        <Panel title="Admin sekolah">
                            <DefinitionList
                                rows={[
                                    ['Nama', tenant.admin.name],
                                    ['Email', tenant.admin.email],
                                ]}
                            />
                            <div className="mt-5">
                                <button
                                    type="button"
                                    className={buttonGhost}
                                    onClick={() =>
                                        setNotice('Contoh tampilan: tautan atur ulang kata sandi dikirim.')
                                    }
                                >
                                    Kirim tautan atur ulang
                                </button>
                            </div>
                        </Panel>
                        <Panel title="Langganan saat ini" className="lg:col-span-2">
                            <DefinitionList
                                rows={[
                                    ['Paket', plan?.label ?? tenant.plan],
                                    ['Siklus', cycleLabel[tenant.cycle]],
                                    ['Status', <StatusChip key="sub" status={tenant.subscriptionStatus} />],
                                    [
                                        'Berakhir',
                                        `${formatDate(tenant.renewsAt)} (${relativeDue(tenant.renewsAt)})`,
                                    ],
                                ]}
                            />
                        </Panel>
                    </div>
                )}

                {tab === 'modul' && (
                    <Panel title="Modul yang diaktifkan">
                        <ul className="divide-y divide-border">
                            {modules.map((module) => {
                                const locked = module.key === 'core';
                                const included = plan?.modules.includes(module.key) ?? false;

                                return (
                                    <li
                                        key={module.key}
                                        className="flex flex-wrap items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                                    >
                                        <label className="flex min-h-11 flex-1 items-center gap-3 text-sm text-foreground">
                                            <input
                                                type="checkbox"
                                                className="size-5 accent-primary"
                                                checked={locked || enabled.includes(module.key)}
                                                disabled={locked || !module.available}
                                                onChange={(event) =>
                                                    setEnabled((current) =>
                                                        event.target.checked
                                                            ? [...current, module.key]
                                                            : current.filter((key) => key !== module.key),
                                                    )
                                                }
                                            />
                                            <span>
                                                {module.label}
                                                <span className="block text-xs text-muted-foreground">
                                                    {locked
                                                        ? 'Selalu aktif'
                                                        : included
                                                          ? 'Termasuk dalam paket'
                                                          : 'Di luar paket'}
                                                </span>
                                            </span>
                                        </label>
                                        {!module.available && <Chip tone="muted">segera hadir</Chip>}
                                    </li>
                                );
                            })}
                        </ul>
                        <div className="mt-5">
                            <button
                                type="button"
                                className={buttonStamp}
                                onClick={() => setNotice('Contoh tampilan: pengaturan modul disimpan.')}
                            >
                                Simpan modul
                            </button>
                        </div>
                    </Panel>
                )}

                {tab === 'izin' && (
                    <div className="flex flex-col gap-6">
                        <p className="text-sm text-muted-foreground">
                            Peran bawaan sekolah dan izin yang melekat padanya.
                            Hanya baca; admin sekolah mengelola peran di
                            tenantnya sendiri.
                        </p>

                        {permissionCatalog.map((group) => (
                            <div key={group.key}>
                                <h2 className="mb-3 text-sm font-semibold text-foreground">
                                    {group.label}
                                </h2>
                                <Table head={['Izin', ...roles.map((role) => role.label)]}>
                                    {group.permissions.map((permission) => (
                                        <tr key={permission}>
                                            <td className="px-4 py-3 font-mono text-xs text-muted-foreground">
                                                {permission}
                                            </td>
                                            {roles.map((role) => (
                                                <td key={role.key} className="px-4 py-3">
                                                    {role.permissions.includes(permission) ? (
                                                        <span className="text-foreground">ya</span>
                                                    ) : (
                                                        <span className="text-muted-foreground/60">tidak</span>
                                                    )}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </Table>
                            </div>
                        ))}

                        {permissionCatalog.length === 0 && (
                            <EmptyState>Belum ada izin terdaftar.</EmptyState>
                        )}
                    </div>
                )}

                {tab === 'langganan' && (
                    <div className="flex flex-col gap-6">
                        <Panel title="Kelola langganan">
                            <DefinitionList
                                rows={[
                                    ['Paket', plan?.label ?? tenant.plan],
                                    [
                                        'Tarif',
                                        plan
                                            ? tenant.cycle === 'yearly'
                                                ? `${formatRupiah(plan.priceYearly)} / tahun`
                                                : `${formatRupiah(plan.priceMonthly)} / bulan`
                                            : '-',
                                    ],
                                    ['Mulai', formatDate(tenant.subscriptionStartedAt)],
                                    ['Berakhir', formatDate(tenant.renewsAt)],
                                    ['Status', <StatusChip key="st" status={tenant.subscriptionStatus} />],
                                ]}
                            />
                            <div className="mt-5 flex flex-wrap gap-3">
                                {['Ganti paket', 'Ubah siklus', 'Perpanjang', 'Beri uji coba'].map((label) => (
                                    <button
                                        key={label}
                                        type="button"
                                        className={buttonGhost}
                                        onClick={() => setNotice(`Contoh tampilan: ${label.toLowerCase()}.`)}
                                    >
                                        {label}
                                    </button>
                                ))}
                            </div>
                        </Panel>

                        <div>
                            <h2 className="mb-3 text-sm font-semibold text-foreground">
                                Riwayat tagihan
                            </h2>
                            {invoices.length === 0 ? (
                                <EmptyState>Belum ada tagihan untuk tenant ini.</EmptyState>
                            ) : (
                                <Table head={['Nomor', 'Terbit', 'Siklus', 'Jumlah', 'Status']}>
                                    {invoices.map((invoice) => (
                                        <tr key={invoice.number}>
                                            <td className="px-4 py-3 font-mono text-xs">{invoice.number}</td>
                                            <td className="px-4 py-3">{formatDate(invoice.issuedAt)}</td>
                                            <td className="px-4 py-3">{cycleLabel[invoice.cycle]}</td>
                                            <td className="px-4 py-3">{formatRupiah(invoice.amount)}</td>
                                            <td className="px-4 py-3">
                                                <StatusChip status={invoice.status} />
                                            </td>
                                        </tr>
                                    ))}
                                </Table>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </ProviderLayout>
    );
}
