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
import type { ConsoleInvoice } from '../../../types/console';

/** Invoice list with a status filter (mock data). */
export default function BillingInvoices({
    invoices,
}: {
    invoices: ConsoleInvoice[];
}) {
    const [status, setStatus] = useState('');

    const rows = useMemo(
        () => invoices.filter((invoice) => status === '' || invoice.status === status),
        [invoices, status],
    );

    return (
        <ProviderLayout>
            <Head title="Tagihan" />

            <PageHeader
                title="Tagihan"
                description="Faktur yang diterbitkan untuk langganan sekolah."
            />

            <div className="mt-8 sm:w-48">
                <select
                    value={status}
                    onChange={(event) => setStatus(event.target.value)}
                    aria-label="Filter status"
                    className={inputClass}
                >
                    <option value="">Semua status</option>
                    <option value="paid">Lunas</option>
                    <option value="unpaid">Belum dibayar</option>
                    <option value="overdue">Menunggak</option>
                    <option value="void">Dibatalkan</option>
                </select>
            </div>

            <div className="mt-6">
                {rows.length === 0 ? (
                    <EmptyState>Tidak ada tagihan dengan status ini.</EmptyState>
                ) : (
                    <Table head={['Nomor', 'Sekolah', 'Terbit', 'Jumlah', 'Status']}>
                        {rows.map((invoice) => (
                            <tr key={invoice.number}>
                                <td className="px-4 py-3 font-mono text-xs">{invoice.number}</td>
                                <td className="px-4 py-3">
                                    <Link
                                        href={consolePath(showTenant.url({ tenant: invoice.tenantId }))}
                                        className="font-medium text-foreground hover:underline"
                                    >
                                        {invoice.tenantName}
                                    </Link>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {cycleLabel[invoice.cycle]}
                                    </p>
                                </td>
                                <td className="px-4 py-3">{formatDate(invoice.issuedAt)}</td>
                                <td className="px-4 py-3">{formatRupiah(invoice.amount)}</td>
                                <td className="px-4 py-3">
                                    <StatusChip status={invoice.status} />
                                </td>
                            </tr>
                        ))}
                    </Table>
                )}
            </div>
        </ProviderLayout>
    );
}
