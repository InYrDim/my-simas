import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { consolePath } from '../../../Components/consolePath';

import { show as showTenant } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';

import ProviderLayout from '../../../Components/ProviderLayout';
import {
    EmptyState,
    PageHeader,
    StatusChip,
    Table,
    inputClass,
} from '../../../Components/ui';
import { cycleLabel, formatDate } from '../../../Components/format';
import type { ConsolePlan, ConsoleTenant } from '../../../types/console';

interface IndexProps {
    tenants: ConsoleTenant[];
    plans: ConsolePlan[];
}

/**
 * Provider console: every school on the platform, searchable by name,
 * slug or school code, filterable by status and plan.
 */
export default function TenantsIndex({ tenants, plans }: IndexProps) {
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('');
    const [plan, setPlan] = useState('');

    const planLabel = (key: string) =>
        plans.find((candidate) => candidate.key === key)?.label ?? key;

    const rows = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return tenants.filter(
            (tenant) =>
                (status === '' || tenant.status === status) &&
                (plan === '' || tenant.plan === plan) &&
                (needle === '' ||
                    tenant.name.toLowerCase().includes(needle) ||
                    tenant.slug.includes(needle) ||
                    tenant.id.toLowerCase().includes(needle)),
        );
    }, [tenants, query, status, plan]);

    return (
        <ProviderLayout>
            <Head title="Tenant" />

            <PageHeader
                title="Tenant"
                description={`${tenants.length} sekolah terdaftar di platform.`}
            />

            <div className="mt-8 grid gap-3 sm:grid-cols-[1fr_12rem_12rem]">
                <input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Cari nama, slug, atau kode sekolah"
                    aria-label="Cari tenant"
                    className={inputClass}
                />
                <select
                    value={status}
                    onChange={(event) => setStatus(event.target.value)}
                    aria-label="Filter status"
                    className={inputClass}
                >
                    <option value="">Semua status</option>
                    <option value="active">Aktif</option>
                    <option value="suspended">Ditangguhkan</option>
                </select>
                <select
                    value={plan}
                    onChange={(event) => setPlan(event.target.value)}
                    aria-label="Filter paket"
                    className={inputClass}
                >
                    <option value="">Semua paket</option>
                    {plans.map((candidate) => (
                        <option key={candidate.key} value={candidate.key}>
                            {candidate.label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="mt-6">
                {rows.length === 0 ? (
                    <EmptyState>Tidak ada tenant yang cocok dengan filter.</EmptyState>
                ) : (
                    <Table
                        head={[
                            'Sekolah',
                            'Status',
                            'Paket',
                            'Langganan',
                            'Pengguna',
                        ]}
                    >
                        {rows.map((tenant) => (
                            <tr key={tenant.id} className="hover:bg-muted">
                                <td className="px-4 py-3">
                                    <Link
                                        href={consolePath(showTenant.url({ tenant: tenant.id }))}
                                        className="font-medium text-foreground hover:underline"
                                    >
                                        {tenant.name}
                                    </Link>
                                    <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                        /{tenant.slug}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    <StatusChip status={tenant.status} />
                                </td>
                                <td className="px-4 py-3">
                                    {planLabel(tenant.plan)}
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {cycleLabel[tenant.cycle]}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    <StatusChip status={tenant.subscriptionStatus} />
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        s.d. {formatDate(tenant.renewsAt)}
                                    </p>
                                </td>
                                <td className="px-4 py-3">{tenant.userCount}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </div>
        </ProviderLayout>
    );
}
