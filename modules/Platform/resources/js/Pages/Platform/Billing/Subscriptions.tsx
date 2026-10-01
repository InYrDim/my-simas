import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { subscriptions as subscriptionsIndex } from '@/actions/Modules/Platform/App/Http/Controllers/BillingController';
import { show as showTenant } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';
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
import {
    cycleLabel,
    formatDate,
    formatRupiah,
} from '../../../Components/format';
import ProviderLayout from '../../../Components/ProviderLayout';
import { applyFilters } from '../../../Components/send';
import type {
    ConsoleSubscription,
    Paginated,
    PlanOption,
} from '../../../types/console';

interface SubscriptionsProps {
    subscriptions: Paginated<ConsoleSubscription>;
    filters: { q: string; status: string; cycle: string; plan: string };
    plans: PlanOption[];
}

/** Every tenant's subscription; actions live on the tenant page. */
export default function BillingSubscriptions({
    subscriptions,
    filters,
    plans,
}: SubscriptionsProps) {
    const [query, setQuery] = useState(filters.q);
    const base = subscriptionsIndex.url();

    function change(next: Partial<typeof filters>) {
        applyFilters(base, { ...filters, q: query, ...next });
    }

    function search(event: FormEvent) {
        event.preventDefault();
        change({});
    }

    return (
        <ProviderLayout>
            <Head title="Daftar langganan" />

            <PageHeader
                title="Daftar langganan"
                description="Ubah paket, siklus, atau perpanjangan dari halaman tenant."
            />

            <form
                onSubmit={search}
                className="mt-8 grid gap-3 sm:grid-cols-[1fr_11rem_11rem_11rem_auto]"
            >
                <Input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Cari nama sekolah"
                    aria-label="Cari sekolah"
                />
                <OptionSelect
                    label="Filter mode"
                    allLabel="Semua mode"
                    value={filters.status}
                    onChange={(status) => change({ status })}
                    options={[
                        { value: 'trial', label: 'Uji coba' },
                        { value: 'active', label: 'Berlangganan' },
                        { value: 'cancelled', label: 'Berhenti' },
                    ]}
                />
                <OptionSelect
                    label="Filter siklus"
                    allLabel="Semua siklus"
                    value={filters.cycle}
                    onChange={(cycle) => change({ cycle })}
                    options={[
                        { value: 'monthly', label: 'Bulanan' },
                        { value: 'yearly', label: 'Tahunan' },
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
                {subscriptions.data.length === 0 ? (
                    <EmptyState>Tidak ada langganan yang cocok.</EmptyState>
                ) : (
                    <DataTable
                        head={['Sekolah', 'Paket', 'Status', 'Periode', 'Tarif']}
                    >
                        {subscriptions.data.map((subscription) => (
                            <TableRow key={subscription.id}>
                                <TableCell>
                                    <Link
                                        href={consolePath(
                                            showTenant.url({
                                                tenant: subscription.tenantId,
                                            }),
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {subscription.tenantName}
                                    </Link>
                                </TableCell>
                                <TableCell>
                                    {subscription.planName}
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {cycleLabel[subscription.cycle]}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    <StatusChip status={subscription.state} />
                                </TableCell>
                                <TableCell className="text-xs text-muted-foreground">
                                    {subscription.status === 'trial'
                                        ? `Uji coba sampai ${subscription.trialEndsAt ? formatDate(subscription.trialEndsAt) : '—'}`
                                        : subscription.periodStart &&
                                            subscription.periodEnd
                                          ? `${formatDate(subscription.periodStart)} – ${formatDate(subscription.periodEnd)}`
                                          : '—'}
                                </TableCell>
                                <TableCell>
                                    {subscription.amount !== null
                                        ? formatRupiah(subscription.amount)
                                        : '—'}
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                )}
                <ListPagination page={subscriptions} />
            </div>
        </ProviderLayout>
    );
}
