import { Head, Link } from '@inertiajs/react';

import { index as applicationsIndex } from '@/actions/Modules/Platform/App/Http/Controllers/ApplicationReviewController';
import { show as showTenant } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';

import { consolePath } from '../../Components/consolePath';
import {
    BarList,
    EmptyState,
    PageHeader,
    Panel,
    StatCard,
    StatusChip,
} from '../../Components/ConsoleParts';
import {
    cycleLabel,
    formatDate,
    formatRupiah,
    relativeDue,
} from '../../Components/format';
import ProviderLayout from '../../Components/ProviderLayout';
import type { ConsoleSubscription, TrendPoint } from '../../types/console';

interface HomeProps {
    stats: {
        activeTenants: number;
        suspendedTenants: number;
        pendingApplications: number;
        trial: number;
        mrr: number;
        endingSoon: number;
    };
    attention: ConsoleSubscription[];
    trend: TrendPoint[];
}

/**
 * Provider dashboard: what needs a decision today, then the platform's
 * shape. Every figure is read from the database.
 */
export default function ProviderHome({ stats, attention, trend }: HomeProps) {
    return (
        <ProviderLayout>
            <Head title="Dashboard" />

            <PageHeader
                title="Dashboard"
                description="Ringkasan platform dan hal yang perlu ditindaklanjuti."
            />

            <div className="mt-8 grid grid-cols-2 gap-3 lg:grid-cols-3">
                <StatCard label="Tenant aktif" value={stats.activeTenants} />
                <StatCard label="Ditangguhkan" value={stats.suspendedTenants} />
                <StatCard
                    label="Pengajuan pending"
                    value={stats.pendingApplications}
                />
                <StatCard label="Sedang uji coba" value={stats.trial} />
                <StatCard
                    label="MRR"
                    value={formatRupiah(stats.mrr)}
                    hint="Pendapatan bulanan berulang"
                />
                <StatCard
                    label="Berakhir ≤ 30 hari"
                    value={stats.endingSoon}
                    hint="Langganan berbayar"
                />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <Panel title="Perlu tindakan">
                    {stats.pendingApplications > 0 && (
                        <Link
                            href={consolePath(applicationsIndex.url())}
                            className="mb-3 flex min-h-11 items-center justify-between gap-3 bg-card px-4 text-sm shadow-sm transition-colors hover:bg-muted"
                        >
                            <span>
                                {stats.pendingApplications} pengajuan sekolah
                                menunggu tinjauan
                            </span>
                            <StatusChip status="pending" />
                        </Link>
                    )}

                    {attention.length === 0 &&
                    stats.pendingApplications === 0 ? (
                        <EmptyState>
                            Tidak ada yang perlu ditindaklanjuti.
                        </EmptyState>
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
                            .map(
                                (point) =>
                                    `${point.month} ${point.newTenants}`,
                            )
                            .join(' · ')}
                    </p>
                </Panel>
            </div>
        </ProviderLayout>
    );
}
