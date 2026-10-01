import { Head, Link } from '@inertiajs/react';

import { show as showTenant } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';

import { consolePath } from '../../../Components/consolePath';
import {
    BarList,
    EmptyState,
    PageHeader,
    Panel,
    StatCard,
    StatusChip,
} from '../../../Components/ConsoleParts';
import {
    cycleLabel,
    formatDate,
    formatRupiah,
    relativeDue,
} from '../../../Components/format';
import ProviderLayout from '../../../Components/ProviderLayout';
import type { ConsoleSubscription, TrendPoint } from '../../../types/console';

interface BillingProps {
    summary: {
        mrr: number;
        arr: number;
        activeSubscriptions: number;
        trial: number;
        trialExpired: number;
        dueOrOverdue: number;
        cancelled: number;
    };
    trend: TrendPoint[];
    attention: ConsoleSubscription[];
}

/** Subscription revenue overview. */
export default function BillingIndex({
    summary,
    trend,
    attention,
}: BillingProps) {
    return (
        <ProviderLayout>
            <Head title="Ringkasan langganan" />

            <PageHeader
                title="Ringkasan langganan"
                description="Pendapatan dan status langganan bulanan atau tahunan tiap sekolah."
            />

            <div className="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-3">
                <StatCard label="MRR" value={formatRupiah(summary.mrr)} />
                <StatCard label="ARR" value={formatRupiah(summary.arr)} />
                <StatCard
                    label="Langganan aktif"
                    value={summary.activeSubscriptions}
                />
                <StatCard label="Uji coba berjalan" value={summary.trial} />
                <StatCard
                    label="Uji coba berakhir"
                    value={summary.trialExpired}
                    hint="Belum berlangganan"
                />
                <StatCard
                    label="Segera berakhir / menunggak"
                    value={summary.dueOrOverdue}
                />
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
                    <p className="mt-4 text-xs text-muted-foreground">
                        Dari tagihan yang sudah lunas. {summary.cancelled}{' '}
                        langganan berhenti.
                    </p>
                </Panel>

                <Panel title="Perlu ditindaklanjuti">
                    {attention.length === 0 ? (
                        <EmptyState>Semua langganan lancar.</EmptyState>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {attention.map((subscription) => (
                                <li key={subscription.id}>
                                    <Link
                                        href={consolePath(
                                            showTenant.url({
                                                tenant: subscription.tenantId,
                                            }),
                                        )}
                                        className="flex items-center justify-between gap-4 bg-card px-4 py-3 shadow-sm transition-colors hover:bg-muted"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {subscription.tenantName}
                                            </p>
                                            <p className="mt-0.5 text-xs text-muted-foreground">
                                                {subscription.planName} ·{' '}
                                                {cycleLabel[subscription.cycle]}
                                                {subscription.endsAt !== null &&
                                                    ` · ${formatDate(subscription.endsAt)} (${relativeDue(subscription.endsAt)})`}
                                            </p>
                                        </div>
                                        <StatusChip
                                            status={subscription.state}
                                        />
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
