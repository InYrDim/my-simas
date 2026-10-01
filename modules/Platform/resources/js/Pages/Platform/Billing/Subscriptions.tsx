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
import { cycleLabel, formatDate, formatRupiah } from '../../../Components/format';
import type { ConsolePlan, ConsoleSubscription } from '../../../types/console';

interface SubscriptionsProps {
    subscriptions: ConsoleSubscription[];
    plans: ConsolePlan[];
}

/** Every tenant's subscription; detail and actions live on the tenant page. */
export default function BillingSubscriptions({
    subscriptions,
    plans,
}: SubscriptionsProps) {
    const [status, setStatus] = useState('');
    const [cycle, setCycle] = useState('');

    const rows = useMemo(
        () =>
            subscriptions.filter(
                (subscription) =>
                    (status === '' || subscription.status === status) &&
                    (cycle === '' || subscription.cycle === cycle),
            ),
        [subscriptions, status, cycle],
    );

    const planLabel = (key: string) =>
        plans.find((plan) => plan.key === key)?.label ?? key;

    return (
        <ProviderLayout>
            <Head title="Daftar langganan" />

            <PageHeader
                title="Langganan"
                description="Ubah paket, siklus, atau perpanjangan dari halaman tenant."
            />

            <div className="mt-8 grid gap-3 sm:grid-cols-[12rem_12rem]">
                <select
                    value={status}
                    onChange={(event) => setStatus(event.target.value)}
                    aria-label="Filter status"
                    className={inputClass}
                >
                    <option value="">Semua status</option>
                    <option value="trial">Uji coba</option>
                    <option value="active">Aktif</option>
                    <option value="due">Jatuh tempo</option>
                    <option value="overdue">Menunggak</option>
                    <option value="cancelled">Berhenti</option>
                </select>
                <select
                    value={cycle}
                    onChange={(event) => setCycle(event.target.value)}
                    aria-label="Filter siklus"
                    className={inputClass}
                >
                    <option value="">Semua siklus</option>
                    <option value="monthly">Bulanan</option>
                    <option value="yearly">Tahunan</option>
                </select>
            </div>

            <div className="mt-6">
                {rows.length === 0 ? (
                    <EmptyState>Tidak ada langganan yang cocok.</EmptyState>
                ) : (
                    <Table head={['Sekolah', 'Paket', 'Status', 'Periode', 'Tarif']}>
                        {rows.map((subscription) => (
                            <tr key={subscription.tenantId} className="hover:bg-muted">
                                <td className="px-4 py-3">
                                    <Link
                                        href={consolePath(showTenant.url({ tenant: subscription.tenantId }))}
                                        className="font-medium text-foreground hover:underline"
                                    >
                                        {subscription.tenantName}
                                    </Link>
                                </td>
                                <td className="px-4 py-3">
                                    {planLabel(subscription.plan)}
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {cycleLabel[subscription.cycle]}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    <StatusChip status={subscription.status} />
                                </td>
                                <td className="px-4 py-3 text-xs text-muted-foreground">
                                    {formatDate(subscription.startedAt)} –{' '}
                                    {formatDate(subscription.endsAt)}
                                </td>
                                <td className="px-4 py-3">{formatRupiah(subscription.amount)}</td>
                            </tr>
                        ))}
                    </Table>
                )}
            </div>
        </ProviderLayout>
    );
}
