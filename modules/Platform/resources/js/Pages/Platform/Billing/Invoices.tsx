import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    confirm as confirmInvoice,
    index as invoicesIndex,
    voidMethod as voidInvoice,
} from '@/actions/Modules/Platform/App/Http/Controllers/InvoiceController';
import { show as showTenant } from '@/actions/Modules/Platform/App/Http/Controllers/TenantConsoleController';
import { Button } from '@shared/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { Textarea } from '@shared/components/ui/textarea';
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

const methodOptions = [
    { value: 'bank_transfer', label: 'Transfer bank' },
    { value: 'cash', label: 'Tunai' },
    { value: 'other', label: 'Lainnya' },
];

/** Local calendar day as YYYY-MM-DD (the date input's format). */
function todayIso(): string {
    return new Date().toLocaleDateString('en-CA');
}

/** Invoices: search, filter, confirm a payment (modal) or void. */
export default function BillingInvoices({ invoices, filters }: InvoicesProps) {
    const [query, setQuery] = useState(filters.q);
    const [confirming, setConfirming] = useState<ConsoleInvoice | null>(null);
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
                                                    setConfirming(invoice)
                                                }
                                            >
                                                Konfirmasi pembayaran
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

            <Dialog
                open={confirming !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirming(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                    {confirming !== null && (
                        <ConfirmPaymentForm
                            key={confirming.id}
                            invoice={confirming}
                            onDone={() => setConfirming(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </ProviderLayout>
    );
}

function ConfirmPaymentForm({
    invoice,
    onDone,
}: {
    invoice: ConsoleInvoice;
    onDone: () => void;
}) {
    const form = useForm({
        method: 'bank_transfer',
        reference: '',
        paid_on: todayIso(),
        note: '',
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.post(consolePath(confirmInvoice.url({ invoice: invoice.id })), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    return (
        <form onSubmit={submit} noValidate>
            <DialogHeader>
                <DialogTitle>Konfirmasi pembayaran</DialogTitle>
                <DialogDescription>
                    {invoice.number} · {invoice.tenantName} ·{' '}
                    {formatRupiah(invoice.amount)}. Hanya nominal pas yang
                    diterima.
                </DialogDescription>
            </DialogHeader>

            <FieldGroup className="my-5">
                <Field data-invalid={!!form.errors.method}>
                    <FieldLabel>Metode pembayaran</FieldLabel>
                    <OptionSelect
                        label="Metode pembayaran"
                        value={form.data.method}
                        onChange={(method) => form.setData('method', method)}
                        options={methodOptions}
                    />
                    <FieldError>{form.errors.method}</FieldError>
                </Field>
                <Field data-invalid={!!form.errors.reference}>
                    <FieldLabel htmlFor="payment-reference">
                        Nomor referensi
                        {form.data.method === 'bank_transfer'
                            ? ''
                            : ' (opsional)'}
                    </FieldLabel>
                    <Input
                        id="payment-reference"
                        value={form.data.reference}
                        onChange={(event) =>
                            form.setData('reference', event.target.value)
                        }
                        aria-invalid={!!form.errors.reference}
                        autoFocus
                    />
                    {form.errors.reference ? (
                        <FieldError>{form.errors.reference}</FieldError>
                    ) : (
                        <FieldDescription>
                            Nomor bukti transfer atau berita transfer.
                        </FieldDescription>
                    )}
                </Field>
                <Field data-invalid={!!form.errors.paid_on}>
                    <FieldLabel htmlFor="payment-paid-on">
                        Tanggal bayar
                    </FieldLabel>
                    <Input
                        id="payment-paid-on"
                        type="date"
                        max={todayIso()}
                        value={form.data.paid_on}
                        onChange={(event) =>
                            form.setData('paid_on', event.target.value)
                        }
                        aria-invalid={!!form.errors.paid_on}
                    />
                    <FieldError>{form.errors.paid_on}</FieldError>
                </Field>
                <Field data-invalid={!!form.errors.note}>
                    <FieldLabel htmlFor="payment-note">
                        Catatan (opsional)
                    </FieldLabel>
                    <Textarea
                        id="payment-note"
                        value={form.data.note}
                        onChange={(event) =>
                            form.setData('note', event.target.value)
                        }
                        aria-invalid={!!form.errors.note}
                    />
                    <FieldError>{form.errors.note}</FieldError>
                </Field>
                <FieldError>{form.errors.billing}</FieldError>
            </FieldGroup>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    Batal
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Tandai lunas
                </Button>
            </DialogFooter>
        </form>
    );
}
