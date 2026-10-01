import { Head, Link } from '@inertiajs/react';
import { consolePath } from '../../Components/consolePath';

import { index as applicationsIndex } from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';
import { show as showTenant } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';

import {
    BarList,
    EmptyState,
    PageHeader,
    Panel,
    StatCard,
    StatusChip,
} from '../../Components/ui';
import ProviderLayout from '../../Components/ProviderLayout';
import {
    cycleLabel,
    formatDate,
    formatRupiah,
    relativeDue,
} from '../../Components/format';
import type {
    ConsoleSubscription,
    TrendPoint,
} from '../../types/console';

interface HomeProps {
    stats: {
        activeTenants: number;
        suspendedTenants: number;
        pendingApplications: number;
        mrr: number;
        endingSoon: number;
    };
    attention: ConsoleSubscription[];
    trend: TrendPoint[];
    activity: { at: string; text: string }[];
}

/**
 * Provider dashboard: what needs a decision today, then the platform's
 * shape. Figures are mock data in the UI-first stage.
 */
export default function ProviderHome({
    stats,
    attention,
    trend,
    activity,
}: HomeProps) {
    return (
        <ProviderLayout>
            <Head title="Dashboard" />

            <PageHeader
                title="Dashboard"
                description="Ringkasan platform dan hal yang perlu ditindaklanjuti."
            />

            <div className="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-5">
                <StatCard label="Tenant aktif" value={stats.activeTenants} />
                <StatCard
                    label="Ditangguhkan"
                    value={stats.suspendedTenants}
                />
                <StatCard
                    label="Pengajuan pending"
                    value={stats.pendingApplications}
                />
                <StatCard
                    label="MRR"
                    value={formatRupiah(stats.mrr)}
                    hint="Pendapatan bulanan berulang"
                />
                <StatCard
                    label="Berakhir ≤ 30 hari"
                    value={stats.endingSoon}
                    hint="Langganan aktif"
                />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <Panel title="Perlu tindakan">
                    {stats.pendingApplications > 0 && (
                        <Link
                            href={consolePath(applicationsIndex.url())}
                            className="mb-3 flex min-h-11 items-center justify-between rounded-lg border border-border px-4 text-sm text-foreground transition-colors hover:border-primary/40 hover:bg-muted"
                        >
                            <span>
                                {stats.pendingApplications} pengajuan sekolah
                                menunggu tinjauan
                            </span>
                            <StatusChip status="pending" />
                        </Link>
                    )}

                    {attention.length === 0 && stats.pendingApplications === 0 ? (
                        <EmptyState>Tidak ada yang perlu ditindaklanjuti.</EmptyState>
                    ) : (
                        <ul className="flex flex-col gap-3">
                            {attention.map((subscription) => (
                                <li key={subscription.tenantId}>
                                    <Link
                                        href={consolePath(showTenant.url({
                                            tenant: subscription.tenantId,
                                        }))}
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

                <Panel title="Pendapatan 6 bulan terakhir">
                    <BarList
                        rows={trend.map((point) => ({
                            label: point.month,
                            value: point.revenue,
                            display: formatRupiah(point.revenue),
                        }))}
                    />
                    <p className="mt-4 text-xs text-muted-foreground">
                        Tenant baru:{' '}
                        {trend
                            .map((point) => `${point.month} ${point.newTenants}`)
                            .join(' · ')}
                    </p>
                </Panel>
            </div>

            <Panel title="Aktivitas terbaru" className="mt-6">
                <ul className="divide-y divide-border">
                    {activity.map((entry) => (
                        <li
                            key={entry.text}
                            className="flex flex-col gap-1 py-3 text-sm text-foreground/80 first:pt-0 last:pb-0 sm:flex-row sm:gap-6"
                        >
                            <span className="w-28 shrink-0 text-xs text-muted-foreground">
                                {formatDate(entry.at)}
                            </span>
                            {entry.text}
                        </li>
                    ))}
                </ul>
            </Panel>
        </ProviderLayout>
    );
}
