import { Head, Link } from '@inertiajs/react';
import { consolePath } from '../../../Components/consolePath';

import { show as showTenant } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';

import ProviderLayout from '../../../Components/ProviderLayout';
import {
    BarList,
    EmptyState,
    PageHeader,
    Panel,
    StatCard,
    StatusChip,
} from '../../../Components/ui';
import { cycleLabel, formatRupiah, relativeDue } from '../../../Components/format';
import type { ConsoleSubscription, TrendPoint } from '../../../types/console';

interface BillingProps {
    summary: {
        mrr: number;
        arr: number;
        activeSubscriptions: number;
        trial: number;
        dueOrOverdue: number;
        cancelled: number;
    };
    trend: TrendPoint[];
    attention: ConsoleSubscription[];
}

/** Subscription revenue overview (mock figures). */
export default function BillingIndex({ summary, trend, attention }: BillingProps) {
    return (
        <ProviderLayout>
            <Head title="Langganan" />

            <PageHeader
                title="Langganan"
                description="Pendapatan dan status langganan bulanan atau tahunan tiap sekolah."
            />

            <div className="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-3">
                <StatCard label="MRR" value={formatRupiah(summary.mrr)} />
                <StatCard label="ARR" value={formatRupiah(summary.arr)} />
                <StatCard label="Langganan aktif" value={summary.activeSubscriptions} />
                <StatCard label="Uji coba" value={summary.trial} />
                <StatCard label="Jatuh tempo / menunggak" value={summary.dueOrOverdue} />
                <StatCard label="Berhenti" value={summary.cancelled} />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <Panel title="Pendapatan 6 bulan terakhir">
                    <BarList
                        rows={trend.map((point) => ({
                            label: point.month,
                            value: point.revenue,
                            display: formatRupiah(point.revenue),
                        }))}
                    />
                </Panel>

                <Panel title="Perlu ditagih">
                    {attention.length === 0 ? (
                        <EmptyState>Semua langganan lancar.</EmptyState>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {attention.map((subscription) => (
                                <li key={subscription.tenantId}>
                                    <Link
                                        href={consolePath(showTenant.url({ tenant: subscription.tenantId }))}
                                        className="flex items-center justify-between gap-4 rounded-lg border border-border px-4 py-3 transition-colors hover:border-primary/40 hover:bg-muted"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium text-foreground">
                                                {subscription.tenantName}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {cycleLabel[subscription.cycle]} ·{' '}
                                                {formatRupiah(subscription.amount)} ·{' '}
                                                {relativeDue(subscription.endsAt)}
                                            </p>
                                        </div>
                                        <StatusChip status={subscription.status} />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Panel>
            </div>
        </ProviderLayout>
    );
}
