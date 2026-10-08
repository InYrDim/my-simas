import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    index as tenantsIndex,
    show as showTenant,
} from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import { consolePath } from '../../../Components/consolePath';
import {
    DataTable,
    EmptyState,
    ListPagination,
    OptionSelect,
    PageHeader,
    StatusChip,
} from '../../../Components/ConsoleParts';
import { cycleLabel, formatDate } from '../../../Components/format';
import ProviderLayout from '../../../Components/ProviderLayout';
import { applyFilters } from '../../../Components/send';
import type {
    Paginated,
    PlanOption,
    TenantListItem,
} from '../../../types/console';

interface IndexProps {
    tenants: Paginated<TenantListItem>;
    filters: { q: string; status: string; plan: string; mode: string };
    plans: PlanOption[];
}

/**
 * Provider console: every school on the platform. Filters run on the
 * server (name/slug/code search, status, plan, trial vs subscribed).
 */
export default function TenantsIndex({ tenants, filters, plans }: IndexProps) {
    const [query, setQuery] = useState(filters.q);
    const base = tenantsIndex.url();

    function change(next: Partial<typeof filters>) {
        applyFilters(base, { ...filters, q: query, ...next });
    }

    function search(event: FormEvent) {
        event.preventDefault();
        change({});
    }

    return (
        <ProviderLayout>
            <Head title="Tenant" />

            <PageHeader
                title="Tenant"
                description={`${tenants.total} sekolah terdaftar di platform.`}
            />

            <form
                onSubmit={search}
                className="mt-8 grid gap-3 sm:grid-cols-[1fr_11rem_11rem_11rem_auto]"
            >
                <Input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Cari nama atau kode sekolah"
                    aria-label="Cari tenant"
                />
                <OptionSelect
                    label="Filter mode"
                    allLabel="Semua mode"
                    value={filters.mode}
                    onChange={(mode) => change({ mode })}
                    options={[
                        { value: 'trial', label: 'Uji coba' },
                        { value: 'subscribed', label: 'Berlangganan' },
                        { value: 'cancelled', label: 'Berhenti' },
                        { value: 'none', label: 'Belum ada langganan' },
                    ]}
                />
                <OptionSelect
                    label="Filter status"
                    allLabel="Semua status"
                    value={filters.status}
                    onChange={(status) => change({ status })}
                    options={[
                        { value: 'active', label: 'Aktif' },
                        { value: 'suspended', label: 'Ditangguhkan' },
                    ]}
                />
                <OptionSelect
                    label="Filter paket"
                    allLabel="Semua paket"
                    value={filters.plan}
                    onChange={(plan) => change({ plan })}
                    options={plans.map((plan) => ({
                        value: plan.key,
                        label: plan.name,
                    }))}
                />
                <Button type="submit" variant="outline">
                    Cari
                </Button>
            </form>

            <div className="mt-6">
                {tenants.data.length === 0 ? (
                    <EmptyState>
                        Tidak ada tenant yang cocok dengan filter.
                    </EmptyState>
                ) : (
                    <DataTable
                        head={[
                            'Sekolah',
                            'Status',
                            'Paket',
                            'Langganan',
                            'Berakhir',
                        ]}
                    >
                        {tenants.data.map((tenant) => (
                            <TableRow key={tenant.id}>
                                <TableCell>
                                    <Link
                                        href={consolePath(
                                            showTenant.url({
                                                tenant: tenant.id,
                                            }),
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {tenant.name}
                                    </Link>
                                    <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                                        {tenant.slug}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <div className="flex flex-wrap gap-1">
                                        <StatusChip status={tenant.status} />
                                        {tenant.overLimit && (
                                            <StatusChip status="over" />
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {tenant.subscription?.planName ?? '—'}
                                    {tenant.subscription !== null && (
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {
                                                cycleLabel[
                                                    tenant.subscription.cycle
                                                ]
                                            }
                                        </p>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {tenant.subscription !== null ? (
                                        <StatusChip
                                            status={tenant.subscription.state}
                                        />
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            belum ada
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="text-xs text-muted-foreground">
                                    {tenant.subscription?.endsAt != null
                                        ? formatDate(tenant.subscription.endsAt)
                                        : '—'}
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                )}
                <ListPagination page={tenants} />
            </div>
        </ProviderLayout>
    );
}
