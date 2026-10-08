import { DownloadIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { Label } from '@shared/components/ui/label';
import { useBilling } from '@shared/hooks/useBilling';
import {
    formatDate,
    formatRupiah,
    invoiceKindLabel,
    invoiceStatusLabel,
} from '@shared/lib/billing';
import type { BillingInvoice } from '@shared/types/billing';

import { Feedback, PanelSkeleton } from './PanelParts';

type Act = ReturnType<typeof useBilling>['act'];

const statusTone: Record<
    BillingInvoice['status'],
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    unpaid: 'outline',
    overdue: 'destructive',
    paid: 'default',
    void: 'secondary',
};

/**
 * "Tagihan & Invoice": the school's invoices, how to pay an unpaid one
 * by bank transfer, and telling the provider about a transfer already
 * made. The provider confirms the payment; this panel never marks an
 * invoice paid.
 */
export default function InvoicesPanel({ active }: { active: boolean }) {
    const { billing, busy, error, notice, act } = useBilling(active);
    const [reporting, setReporting] = useState<string | null>(null);

    if (billing === undefined) {
        return <PanelSkeleton />;
    }

    if (billing === null) {
        return (
            <p className="text-muted-foreground">
                Anda tidak punya izin untuk melihat tagihan sekolah.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-4">
            <Feedback error={error} notice={notice} />

            {billing.invoices.length === 0 ? (
                <p className="text-muted-foreground">Belum ada invoice.</p>
            ) : (
                <ul className="flex flex-col divide-y divide-border">
                    {billing.invoices.map((invoice) => (
                        <InvoiceRow
                            key={invoice.number}
                            invoice={invoice}
                            busy={busy}
                            act={act}
                            reporting={reporting === invoice.number}
                            onReport={(open) =>
                                setReporting(open ? invoice.number : null)
                            }
                        />
                    ))}
                </ul>
            )}
        </div>
    );
}

function InvoiceRow({
    invoice,
    busy,
    act,
    reporting,
    onReport,
}: {
    invoice: BillingInvoice;
    busy: boolean;
    act: Act;
    reporting: boolean;
    onReport: (open: boolean) => void;
}) {
    return (
        <li className="flex flex-col gap-3 py-3 first:pt-0 last:pb-0">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="font-mono text-xs">{invoice.number}</p>
                    <p className="text-xs text-muted-foreground">
                        {invoiceKindLabel[invoice.kind]} · {invoice.planName} ·{' '}
                        {formatDate(invoice.periodStart)} –{' '}
                        {formatDate(invoice.periodEnd)}
                    </p>
                    <p className="text-sm font-medium">
                        {formatRupiah(invoice.amount)}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {invoice.paidOn
                            ? `Dibayar ${formatDate(invoice.paidOn)}`
                            : `Jatuh tempo ${formatDate(invoice.dueOn)}`}
                    </p>
                </div>
                <div className="flex shrink-0 flex-col items-end gap-2">
                    <Badge variant={statusTone[invoice.status]}>
                        {invoiceStatusLabel[invoice.status]}
                    </Badge>
                    <div className="flex gap-2">
                        {invoice.payable &&
                            invoice.instructions === null &&
                            invoice.urls !== null && (
                                <Button
                                    size="sm"
                                    disabled={busy}
                                    onClick={() =>
                                        act('post', invoice.urls!.pay)
                                    }
                                >
                                    Bayar
                                </Button>
                            )}
                        {invoice.urls !== null && (
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={invoice.urls.pdf}
                                    aria-label={`Unduh PDF ${invoice.number}`}
                                >
                                    <DownloadIcon />
                                    PDF
                                </a>
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            {invoice.payable &&
                invoice.instructions !== null &&
                invoice.urls !== null && (
                    <Instructions
                        invoice={invoice}
                        busy={busy}
                        act={act}
                        reporting={reporting}
                        onReport={onReport}
                    />
                )}
        </li>
    );
}

function Instructions({
    invoice,
    busy,
    act,
    reporting,
    onReport,
}: {
    invoice: BillingInvoice;
    busy: boolean;
    act: Act;
    reporting: boolean;
    onReport: (open: boolean) => void;
}) {
    const instructions = invoice.instructions!;
    const reported = instructions.reportedTransfer;

    return (
        <div className="flex flex-col gap-3 border border-border bg-muted/40 px-3 py-3 text-sm">
            <p className="font-medium">Cara membayar</p>
            <dl className="grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
                <dt className="text-muted-foreground">Nominal</dt>
                <dd className="font-medium">
                    {formatRupiah(instructions.amount)}
                </dd>
                <dt className="text-muted-foreground">Bank</dt>
                <dd>{instructions.bankName ?? '—'}</dd>
                <dt className="text-muted-foreground">No. rekening</dt>
                <dd className="font-mono">{instructions.bankAccount ?? '—'}</dd>
                <dt className="text-muted-foreground">Atas nama</dt>
                <dd>{instructions.accountHolder ?? '—'}</dd>
            </dl>
            <p className="text-xs text-muted-foreground">{instructions.note}</p>

            {reported !== null && (
                <p className="text-xs">
                    Laporan transfer terkirim:{' '}
                    {formatDate(reported.transferredOn)} lewat {reported.bank}{' '}
                    a.n. {reported.senderName}
                    {reported.reference
                        ? `, referensi ${reported.reference}`
                        : ''}
                    . Menunggu konfirmasi.
                </p>
            )}

            {reporting ? (
                <ReportForm
                    invoice={invoice}
                    busy={busy}
                    act={act}
                    onDone={() => onReport(false)}
                />
            ) : (
                <Button
                    variant="outline"
                    size="sm"
                    className="self-start"
                    onClick={() => onReport(true)}
                >
                    {reported === null
                        ? 'Saya sudah transfer'
                        : 'Perbarui laporan transfer'}
                </Button>
            )}
        </div>
    );
}

function ReportForm({
    invoice,
    busy,
    act,
    onDone,
}: {
    invoice: BillingInvoice;
    busy: boolean;
    act: Act;
    onDone: () => void;
}) {
    const today = new Date().toLocaleDateString('en-CA');
    const [transferredOn, setTransferredOn] = useState(today);
    const [bank, setBank] = useState('');
    const [senderName, setSenderName] = useState('');
    const [reference, setReference] = useState('');

    function submit(event: FormEvent) {
        event.preventDefault();

        act(
            'post',
            invoice.urls!.reportTransfer,
            {
                transferred_on: transferredOn,
                bank,
                sender_name: senderName,
                reference,
            },
            onDone,
        );
    }

    const id = (name: string) => `report-${invoice.number}-${name}`;

    return (
        <form onSubmit={submit} className="flex flex-col gap-3" noValidate>
            <div className="grid gap-3 sm:grid-cols-2">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={id('date')}>Tanggal transfer</Label>
                    <Input
                        id={id('date')}
                        type="date"
                        max={today}
                        required
                        value={transferredOn}
                        onChange={(event) =>
                            setTransferredOn(event.target.value)
                        }
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={id('bank')}>Bank pengirim</Label>
                    <Input
                        id={id('bank')}
                        required
                        value={bank}
                        onChange={(event) => setBank(event.target.value)}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={id('sender')}>Nama pengirim</Label>
                    <Input
                        id={id('sender')}
                        required
                        value={senderName}
                        onChange={(event) => setSenderName(event.target.value)}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor={id('ref')}>
                        Nomor referensi (bila ada)
                    </Label>
                    <Input
                        id={id('ref')}
                        value={reference}
                        onChange={(event) => setReference(event.target.value)}
                    />
                </div>
            </div>
            <div className="flex gap-2">
                <Button
                    type="submit"
                    size="sm"
                    disabled={busy || bank === '' || senderName === ''}
                >
                    Kirim laporan
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onDone}
                >
                    Batal
                </Button>
            </div>
        </form>
    );
}
