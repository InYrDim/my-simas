import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    index as invoicesIndex,
    pay as payInvoice,
    voidMethod as voidInvoice,
} from '@/actions/Modules/Platform/App/Http/Controllers/InvoiceController';
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
import { applyFilters, send } from '../../../Components/send';
import type { ConsoleInvoice, Paginated } from '../../../types/console';

interface InvoicesProps {
    invoices: Paginated<ConsoleInvoice>;
    filters: { q: string; status: string };
}

/** Invoices: search, filter, mark paid (gateway) or void. */
export default function BillingInvoices({ invoices, filters }: InvoicesProps) {
    const [query, setQuery] = useState(filters.q);
    const base = invoicesIndex.url();

    function change(next: Partial<typeof filters>) {
        applyFilters(base, { ...filters, q: query, ...next });
    }

    function search(event: FormEvent) {
        event.preventDefault();
        change({});
    }

    return (
        <ProviderLayout>
            <Head title="Tagihan" />

            <PageHeader
                title="Tagihan"
                description="Faktur yang diterbitkan untuk langganan sekolah."
            />

            <form
                onSubmit={search}
                className="mt-8 grid gap-3 sm:grid-cols-[1fr_12rem_auto]"
            >
                <Input
                    type="search"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder="Cari nomor tagihan atau nama sekolah"
                    aria-label="Cari tagihan"
                />
                <OptionSelect
                    label="Filter status"
                    allLabel="Semua status"
                    value={filters.status}
                    onChange={(status) => change({ status })}
                    options={[
                        { value: 'unpaid', label: 'Belum dibayar' },
                        { value: 'overdue', label: 'Lewat jatuh tempo' },
                        { value: 'paid', label: 'Lunas' },
                        { value: 'void', label: 'Dibatalkan' },
                    ]}
                />
                <Button type="submit" variant="outline">
                    Cari
                </Button>
            </form>

            <div className="mt-6">
                {invoices.data.length === 0 ? (
                    <EmptyState>Tidak ada tagihan yang cocok.</EmptyState>
                ) : (
                    <DataTable
                        head={[
                            'Nomor',
                            'Sekolah',
                            'Terbit',
                            'Jumlah',
                            'Status',
                            '',
                        ]}
                    >
                        {invoices.data.map((invoice) => (
                            <TableRow key={invoice.id}>
                                <TableCell className="font-mono text-xs">
                                    {invoice.number}
                                </TableCell>
                                <TableCell>
                                    <Link
                                        href={consolePath(
                                            showTenant.url({
                                                tenant: invoice.tenantId,
                                            }),
                                        )}
                                        className="font-medium hover:underline"
                                    >
                                        {invoice.tenantName}
                                    </Link>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        {invoice.planName} ·{' '}
                                        {cycleLabel[invoice.cycle]}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    {formatDate(invoice.issuedAt)}
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        jatuh tempo {formatDate(invoice.dueAt)}
                                    </p>
                                </TableCell>
                                <TableCell>
                                    {formatRupiah(invoice.amount)}
                                </TableCell>
                                <TableCell>
                                    <StatusChip status={invoice.state} />
                                </TableCell>
                                <TableCell>
                                    {invoice.status === 'unpaid' && (
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    send(
                                                        'post',
                                                        payInvoice.url({
                                                            invoice: invoice.id,
                                                        }),
                                                    )
                                                }
                                            >
                                                Tandai lunas
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    send(
                                                        'post',
                                                        voidInvoice.url({
                                                            invoice: invoice.id,
                                                        }),
                                                    )
                                                }
                                            >
                                                Batalkan
                                            </Button>
                                        </div>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                )}
                <ListPagination page={invoices} />
            </div>
        </ProviderLayout>
    );
}
