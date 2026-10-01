import { MessageCircleIcon, QrCodeIcon, SmartphoneIcon } from 'lucide-react';
import { useState } from 'react';

import { DataTable, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import { Field, FieldDescription, FieldLabel } from '@shared/components/ui/field';
import { Textarea } from '@shared/components/ui/textarea';
import { TableCell, TableRow } from '@shared/components/ui/table';

import FormDialog from '../../../../Components/FormDialog';
import MasterPage from '../../../../Components/MasterPage';
import type { SchoolSummary } from '../../../../types/master';

interface WhatsappProps {
    school: SchoolSummary;
    connection: {
        connected: boolean;
        number: string;
        deviceName: string;
        lastSyncLabel: string;
        sentThisMonth: number;
        quota: number;
    };
    notifications: {
        key: string;
        title: string;
        description: string;
        recipient: string;
        enabled: boolean;
    }[];
    template: { body: string; variables: string[]; sample: Record<string, string> };
    history: { id: number; sentAt: string; to: string; kind: string; status: 'read' | 'delivered' | 'failed' }[];
}

const historyStatus = {
    read: ['default', 'Dibaca'],
    delivered: ['secondary', 'Terkirim'],
    failed: ['destructive', 'Gagal'],
} as const;

function fillTemplate(body: string, sample: Record<string, string>): string {
    return body.replace(/\{(\w+)\}/g, (match, key: string) => sample[key] ?? match);
}

/** A stand-in QR code: a fixed pattern, never a real pairing code. */
function QrPlaceholder() {
    const cells = Array.from({ length: 169 }, (_, index) => (index * 7 + Math.floor(index / 13) * 3) % 5 < 2);

    return (
        <div
            role="img"
            aria-label="Contoh kode QR"
            className="mx-auto grid size-44 grid-cols-13 gap-px border border-border bg-card p-2"
            style={{ gridTemplateColumns: 'repeat(13, minmax(0, 1fr))' }}
        >
            {cells.map((filled, index) => (
                <span key={index} className={filled ? 'bg-foreground' : 'bg-transparent'} />
            ))}
        </div>
    );
}

/**
 * Integrasi › WhatsApp: connect the school number, choose which events send
 * a message and to whom, edit the message wording, and see recent sends.
 */
export default function WhatsappIndex({ school, connection, notifications, template, history }: WhatsappProps) {
    const [connected, setConnected] = useState(connection.connected);
    const [enabled, setEnabled] = useState<Record<string, boolean>>(
        Object.fromEntries(notifications.map((item) => [item.key, item.enabled])),
    );
    const [body, setBody] = useState(template.body);
    const [saved, setSaved] = useState(false);

    const usedPercent = Math.round((connection.sentThisMonth / connection.quota) * 100);

    return (
        <MasterPage
            school={school}
            title="WhatsApp"
            description="Kirim pemberitahuan sekolah lewat WhatsApp ke wali murid dan staf."
            width="max-w-5xl"
        >
            <Panel>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        <span className="flex size-11 shrink-0 items-center justify-center bg-primary/10 text-primary">
                            <MessageCircleIcon className="size-5" aria-hidden />
                        </span>
                        <div>
                            <div className="flex items-center gap-3">
                                <h2 className="text-base font-semibold">
                                    {connected ? connection.number : 'Belum terhubung'}
                                </h2>
                                <Badge variant={connected ? 'default' : 'outline'}>
                                    {connected ? 'Terhubung' : 'Belum terhubung'}
                                </Badge>
                            </div>
                            <p className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
                                <SmartphoneIcon className="size-4" aria-hidden />
                                {connected
                                    ? `${connection.deviceName} · sinkron ${connection.lastSyncLabel}`
                                    : 'Hubungkan nomor WhatsApp sekolah untuk mulai mengirim pesan.'}
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-3">
                        {connected ? (
                            <Button variant="outline" onClick={() => setConnected(false)}>
                                Putuskan
                            </Button>
                        ) : (
                            <FormDialog
                                title="Hubungkan WhatsApp"
                                description="Pindai kode ini dari aplikasi WhatsApp di ponsel sekolah."
                                submitLabel="Saya sudah memindai"
                                trigger={
                                    <Button>
                                        <QrCodeIcon />
                                        Hubungkan WhatsApp
                                    </Button>
                                }
                            >
                                <QrPlaceholder />
                                <ol className="list-decimal pl-5 text-sm text-muted-foreground">
                                    <li>Buka WhatsApp di ponsel.</li>
                                    <li>Pilih Perangkat tertaut, lalu Tautkan perangkat.</li>
                                    <li>Arahkan kamera ke kode di atas.</li>
                                </ol>
                            </FormDialog>
                        )}
                    </div>
                </div>

                {connected && (
                    <div className="mt-6 border-t border-border pt-4">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted-foreground">Pesan terkirim bulan ini</span>
                            <span className="font-medium">
                                {connection.sentThisMonth} / {connection.quota}
                            </span>
                        </div>
                        <div
                            role="progressbar"
                            aria-label="Kuota pesan bulan ini"
                            aria-valuenow={connection.sentThisMonth}
                            aria-valuemin={0}
                            aria-valuemax={connection.quota}
                            className="mt-2 h-2 bg-muted"
                        >
                            <div className="h-full bg-primary" style={{ width: `${usedPercent}%` }} />
                        </div>
                    </div>
                )}
            </Panel>

            <div className="mt-6 grid gap-6 lg:grid-cols-5">
                <Panel title="Pemberitahuan otomatis" className="lg:col-span-3">
                    {!connected && (
                        <p className="mb-4 text-sm text-muted-foreground">
                            Hubungkan WhatsApp dulu agar pemberitahuan bisa diaktifkan.
                        </p>
                    )}
                    <ul className="flex flex-col divide-y divide-border">
                        {notifications.map((item) => (
                            <li key={item.key} className="py-3 first:pt-0 last:pb-0">
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id={`wa-${item.key}`}
                                        checked={connected && enabled[item.key]}
                                        disabled={!connected}
                                        onCheckedChange={(checked) =>
                                            setEnabled((current) => ({ ...current, [item.key]: checked === true }))
                                        }
                                    />
                                    <div className="flex-1">
                                        <FieldLabel htmlFor={`wa-${item.key}`}>{item.title}</FieldLabel>
                                        <FieldDescription>{item.description}</FieldDescription>
                                    </div>
                                    <Badge variant="secondary">{item.recipient}</Badge>
                                </Field>
                            </li>
                        ))}
                    </ul>
                </Panel>

                <Panel title="Isi pesan: siswa tidak hadir" className="lg:col-span-2">
                    <Field>
                        <FieldLabel htmlFor="wa-template">Teks pesan</FieldLabel>
                        <Textarea
                            id="wa-template"
                            rows={5}
                            value={body}
                            onChange={(event) => {
                                setSaved(false);
                                setBody(event.target.value);
                            }}
                        />
                        <FieldDescription>
                            Variabel:{' '}
                            {template.variables.map((variable) => (
                                <code key={variable} className="mr-1 font-mono text-xs">
                                    {`{${variable}}`}
                                </code>
                            ))}
                        </FieldDescription>
                    </Field>

                    <div className="mt-4 border border-border bg-muted p-3 text-sm">
                        <p className="mb-1 text-xs font-medium text-muted-foreground">Pratinjau</p>
                        {fillTemplate(body, template.sample)}
                    </div>

                    <div className="mt-4 flex items-center justify-end gap-3">
                        {saved && (
                            <span role="status" className="text-sm text-muted-foreground">
                                Contoh saja — belum tersimpan.
                            </span>
                        )}
                        <Button variant="outline" disabled={!connected}>
                            Kirim pesan uji
                        </Button>
                        <Button onClick={() => setSaved(true)}>Simpan</Button>
                    </div>
                </Panel>
            </div>

            <h2 className="mt-10 mb-3 text-sm font-semibold">Riwayat pesan</h2>
            <DataTable head={['Waktu', 'Tujuan', 'Jenis', 'Status']}>
                {history.map((row) => {
                    const [variant, label] = historyStatus[row.status];

                    return (
                        <TableRow key={row.id}>
                            <TableCell>{row.sentAt}</TableCell>
                            <TableCell className="font-mono text-xs">{row.to}</TableCell>
                            <TableCell>{row.kind}</TableCell>
                            <TableCell>
                                <Badge variant={variant}>{label}</Badge>
                            </TableCell>
                        </TableRow>
                    );
                })}
            </DataTable>
        </MasterPage>
    );
}
