import { router, useForm, usePage, usePoll } from '@inertiajs/react';
import { MessageCircleIcon, QrCodeIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    connect as connectWhatsapp,
    disconnect as disconnectWhatsapp,
    index as whatsappIndex,
    request as requestWhatsapp,
    test as testWhatsapp,
    updateNotice,
} from '@/actions/Modules/Core/App/Http/Controllers/WhatsappController';
import ListPager from '@shared/components/ListPager';
import type { Pagination } from '@shared/components/ListPager';
import { DataTable, EmptyState, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
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
    FieldLabel,
} from '@shared/components/ui/field';
import { Spinner } from '@shared/components/ui/spinner';
import { Textarea } from '@shared/components/ui/textarea';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../../../Components/ConfirmAction';
import FormDialog from '../../../../Components/FormDialog';
import { InputField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type { SchoolSummary } from '../../../../types/master';

/** Where the school's WhatsApp stands. It never carries a gateway key. */
interface WhatsappState {
    stage: 'none' | 'pending' | 'rejected' | 'disabled' | 'active';
    /** The gateway's session status (`created`, `ready`, ...), only while active. */
    connection: string | null;
    phone: string | null;
    pushName: string | null;
    /** Why the provider rejected or disabled it. */
    note: string | null;
    qrCode: string | null;
    lastError: string | null;
    requestedAt: string | null;
}

interface WhatsappProps {
    school: SchoolSummary;
    state: WhatsappState;
    kinds: NoticeKind[];
    history: MessageRow[];
    pagination: Pagination;
}

/**
 * A kind of notice a module can send. `available: false` is one that is
 * announced but not built yet ("Segera hadir").
 */
interface NoticeKind {
    key: string;
    title: string;
    description: string;
    recipient: string;
    available: boolean;
    enabled: boolean;
    /** The wording in use: the school's own, or the default. */
    template: string;
    defaultTemplate: string;
    /** Variable name to a sample value, for the preview. */
    sample: Record<string, string>;
}

/** One line of the message log; the number arrives masked. */
interface MessageRow {
    id: number;
    at: string;
    recipient: string;
    to: string;
    kind: string;
    status: 'pending' | 'sent' | 'failed' | 'unsent' | 'no_recipient';
    error: string | null;
}

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const historyStatus: Record<MessageRow['status'], [BadgeVariant, string]> = {
    pending: ['outline', 'Dalam antrean'],
    sent: ['default', 'Terkirim ke WhatsApp'],
    failed: ['destructive', 'Gagal'],
    unsent: ['secondary', 'WhatsApp belum terhubung'],
    no_recipient: ['secondary', 'Tanpa nomor tujuan'],
};

const dayFormat = new Intl.DateTimeFormat('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

function fillTemplate(body: string, sample: Record<string, string>): string {
    return body.replace(
        /\{(\w+)\}/g,
        (match, key: string) => sample[key] ?? match,
    );
}

/** Heading, badge and explanation of the status panel, per stage. */
function describe(state: WhatsappState): {
    heading: string;
    badge: [BadgeVariant, string];
    text: string;
} {
    switch (state.stage) {
        case 'pending':
            return {
                heading: 'Menunggu persetujuan',
                badge: ['outline', 'Menunggu'],
                text:
                    state.requestedAt !== null
                        ? `Diajukan ${dayFormat.format(new Date(state.requestedAt))}. Penyedia layanan akan meninjau pengajuan ini.`
                        : 'Penyedia layanan akan meninjau pengajuan ini.',
            };
        case 'rejected':
            return {
                heading: 'Pengajuan ditolak',
                badge: ['destructive', 'Ditolak'],
                text:
                    state.note ??
                    'Penyedia layanan menolak pengajuan ini tanpa catatan.',
            };
        case 'disabled':
            return {
                heading: 'WhatsApp dinonaktifkan',
                badge: ['destructive', 'Dinonaktifkan'],
                text:
                    state.note ??
                    'Penyedia layanan menonaktifkan WhatsApp sekolah ini.',
            };
        case 'active':
            return describeConnection(state);
        default:
            return {
                heading: 'Belum diajukan',
                badge: ['outline', 'Belum aktif'],
                text: 'Ajukan WhatsApp ke penyedia layanan. Setelah disetujui, nomor WhatsApp sekolah bisa dihubungkan di sini.',
            };
    }
}

/** The same, for an approved WhatsApp: where the link to a number stands. */
function describeConnection(state: WhatsappState): {
    heading: string;
    badge: [BadgeVariant, string];
    text: string;
} {
    switch (state.connection) {
        case 'ready':
            return {
                heading: state.phone ?? 'Terhubung',
                badge: ['default', 'Terhubung'],
                text: state.pushName ?? 'Nomor WhatsApp sekolah terhubung.',
            };
        case 'initializing':
            return {
                heading: 'Menyiapkan sambungan',
                badge: ['outline', 'Menghubungkan'],
                text: 'Kode QR akan muncul di sini dalam beberapa saat.',
            };
        case 'qr_ready':
            return {
                heading: 'Pindai kode QR',
                badge: ['outline', 'Menunggu pindai'],
                text: 'Pindai kode di bawah dari WhatsApp di ponsel sekolah. Kode berganti sendiri.',
            };
        case 'authenticating':
            return {
                heading: 'Menautkan perangkat',
                badge: ['outline', 'Menghubungkan'],
                text: 'Kode sudah dipindai. Tunggu sampai WhatsApp selesai menautkan.',
            };
        case 'failed':
            return {
                heading: 'Sambungan gagal',
                badge: ['destructive', 'Gagal'],
                text: 'WhatsApp tidak berhasil dihubungkan. Coba hubungkan lagi.',
            };
        case 'action_required':
            return {
                heading: 'Perlu dihubungkan lagi',
                badge: ['destructive', 'Perlu tindakan'],
                text: 'WhatsApp meminta perangkat ditautkan ulang. Hubungkan lagi untuk memindai kode baru.',
            };
        case 'disconnected':
            return {
                heading: 'Tidak terhubung',
                badge: ['secondary', 'Terputus'],
                text: 'Nomor WhatsApp sekolah sedang tidak terhubung.',
            };
        default:
            return {
                heading: 'Disetujui',
                badge: ['secondary', 'Belum terhubung'],
                text: 'WhatsApp sekolah sudah disetujui. Hubungkan nomor WhatsApp sekolah untuk mulai mengirim pesan.',
            };
    }
}

/** Link steps that finish on their own: the page keeps asking meanwhile. */
const linking = ['initializing', 'qr_ready', 'authenticating'];

/** Re-reads the given props every few seconds for as long as it is mounted. */
function Poll({ only }: { only: string[] }) {
    usePoll(3000, { only });

    return null;
}

/**
 * Integrasi › WhatsApp: ask the provider for WhatsApp, then link the
 * school's number by scanning a QR. Below it: which notices go out and
 * how they read, and the log of what was sent.
 */
export default function WhatsappIndex({
    school,
    state,
    kinds,
    history,
    pagination,
}: WhatsappProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const active = state.stage === 'active';
    const connected = active && state.connection === 'ready';
    const underWay =
        active &&
        state.connection !== null &&
        linking.includes(state.connection);
    const queued = history.some((row) => row.status === 'pending');
    const status = describe(state);
    const [requesting, setRequesting] = useState(false);
    const [editing, setEditing] = useState<NoticeKind | null>(null);

    function toggle(kind: NoticeKind, enabled: boolean) {
        router.put(
            updateNotice.url(kind.key),
            { enabled, template: kind.template },
            { preserveScroll: true },
        );
    }

    function post(url: string) {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setRequesting(true),
                onFinish: () => setRequesting(false),
            },
        );
    }

    return (
        <MasterPage
            school={school}
            title="WhatsApp"
            description="Kirim pemberitahuan sekolah lewat WhatsApp ke wali murid dan staf."
            width="max-w-5xl"
            mock={false}
            writePermission="core.integration.manage"
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
                                    {status.heading}
                                </h2>
                                <Badge variant={status.badge[0]}>
                                    {status.badge[1]}
                                </Badge>
                            </div>
                            <p className="mt-1 max-w-prose text-sm text-muted-foreground">
                                {status.text}
                            </p>
                        </div>
                    </div>

                    {(state.stage === 'none' || state.stage === 'rejected') && (
                        <Button
                            onClick={() => post(requestWhatsapp.url())}
                            disabled={requesting}
                        >
                            {state.stage === 'rejected'
                                ? 'Ajukan lagi'
                                : 'Ajukan WhatsApp'}
                        </Button>
                    )}

                    {active && !connected && !underWay && (
                        <Button
                            onClick={() => post(connectWhatsapp.url())}
                            disabled={requesting}
                        >
                            <QrCodeIcon />
                            {state.connection === 'failed' ||
                            state.connection === 'action_required'
                                ? 'Hubungkan lagi'
                                : 'Hubungkan'}
                        </Button>
                    )}

                    {underWay && (
                        <Button
                            variant="outline"
                            onClick={() => post(disconnectWhatsapp.url())}
                            disabled={requesting}
                        >
                            Batal
                        </Button>
                    )}

                    {connected && (
                        <FormDialog
                            title="Kirim pesan uji"
                            description="Satu pesan singkat dikirim dari nomor sekolah ke nomor ini."
                            route={testWhatsapp()}
                            submitLabel="Kirim"
                            trigger={<Button>Kirim pesan uji</Button>}
                        >
                            <InputField
                                id="wa-test-phone"
                                name="phone"
                                label="Nomor WhatsApp tujuan"
                                type="tel"
                                inputMode="tel"
                                autoComplete="off"
                                placeholder="0812-3456-7890"
                                required
                            />
                        </FormDialog>
                    )}

                    {connected && (
                        <ConfirmAction
                            route={disconnectWhatsapp()}
                            title="Putuskan WhatsApp sekolah?"
                            description="Pesan tidak bisa dikirim sampai nomor dihubungkan lagi dengan memindai kode QR baru."
                            confirmLabel="Putuskan"
                            trigger={
                                <Button variant="outline">Putuskan</Button>
                            }
                        />
                    )}
                </div>

                {state.lastError !== null &&
                    state.lastError !== errors?.status && (
                        <Alert variant="destructive" className="mt-4">
                            <AlertDescription>
                                {state.lastError}
                            </AlertDescription>
                        </Alert>
                    )}

                {underWay && (
                    <div className="mt-6 border-t border-border pt-6">
                        <Poll only={['state']} />
                        {state.qrCode !== null ? (
                            <div className="flex flex-wrap items-center gap-6">
                                <img
                                    src={state.qrCode}
                                    alt="Kode QR untuk menautkan WhatsApp"
                                    className="size-56 border border-border bg-white p-2"
                                />
                                <ol className="list-decimal pl-5 text-sm text-muted-foreground">
                                    <li>Buka WhatsApp di ponsel sekolah.</li>
                                    <li>
                                        Pilih Perangkat tertaut, lalu Tautkan
                                        perangkat.
                                    </li>
                                    <li>Arahkan kamera ke kode ini.</li>
                                </ol>
                            </div>
                        ) : (
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Spinner aria-label="Memuat" />
                                {state.connection === 'authenticating'
                                    ? 'Menautkan perangkat…'
                                    : 'Menyiapkan kode QR…'}
                            </p>
                        )}
                    </div>
                )}
            </Panel>

            <h2 className="mt-10 mb-3 text-sm font-semibold">
                Pemberitahuan otomatis
            </h2>
            {kinds.length === 0 ? (
                <EmptyState>Belum ada pemberitahuan yang tersedia.</EmptyState>
            ) : (
                <Panel>
                    {!connected && (
                        <p className="mb-4 text-sm text-muted-foreground">
                            Pemberitahuan yang aktif baru terkirim setelah
                            WhatsApp sekolah terhubung.
                        </p>
                    )}
                    <ul className="flex flex-col divide-y divide-border">
                        {kinds.map((kind) => (
                            <li
                                key={kind.key}
                                className="py-3 first:pt-0 last:pb-0"
                            >
                                <Field orientation="horizontal">
                                    <Checkbox
                                        id={`wa-${kind.key}`}
                                        checked={kind.enabled}
                                        disabled={!kind.available}
                                        onCheckedChange={(checked) =>
                                            toggle(kind, checked === true)
                                        }
                                    />
                                    <div className="flex-1">
                                        <FieldLabel htmlFor={`wa-${kind.key}`}>
                                            {kind.title}
                                        </FieldLabel>
                                        <FieldDescription>
                                            {kind.description}
                                        </FieldDescription>
                                    </div>
                                    <Badge variant="secondary">
                                        {kind.recipient}
                                    </Badge>
                                    {kind.available ? (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setEditing(kind)}
                                        >
                                            Ubah isi pesan
                                        </Button>
                                    ) : (
                                        <Badge variant="outline">
                                            Segera hadir
                                        </Badge>
                                    )}
                                </Field>
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditing(null);
                    }
                }}
            >
                <DialogContent className="sm:max-w-xl">
                    {editing !== null && (
                        <TemplateForm
                            key={editing.key}
                            kind={editing}
                            onDone={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <h2 className="mt-10 mb-3 text-sm font-semibold">Riwayat pesan</h2>
            {queued && <Poll only={['history', 'pagination']} />}
            {history.length === 0 ? (
                <EmptyState>Belum ada pesan yang dikirim.</EmptyState>
            ) : (
                <DataTable
                    head={['Waktu', 'Penerima', 'Nomor', 'Jenis', 'Status']}
                >
                    {history.map((row) => {
                        const [variant, label] = historyStatus[row.status];

                        return (
                            <TableRow key={row.id}>
                                <TableCell>{row.at}</TableCell>
                                <TableCell>{row.recipient}</TableCell>
                                <TableCell className="font-mono text-xs">
                                    {row.to}
                                </TableCell>
                                <TableCell>{row.kind}</TableCell>
                                <TableCell>
                                    <Badge variant={variant}>{label}</Badge>
                                    {row.error !== null &&
                                        row.status !== 'sent' && (
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {row.error}
                                            </span>
                                        )}
                                </TableCell>
                            </TableRow>
                        );
                    })}
                </DataTable>
            )}
            <ListPager
                url={whatsappIndex.url()}
                filters={{}}
                pagination={pagination}
            />
        </MasterPage>
    );
}

/** Reword one kind of notice, with a preview on sample values. */
function TemplateForm({
    kind,
    onDone,
}: {
    kind: NoticeKind;
    onDone: () => void;
}) {
    const form = useForm({ enabled: kind.enabled, template: kind.template });

    function submit(event: FormEvent) {
        event.preventDefault();

        form.put(updateNotice.url(kind.key), {
            preserveScroll: true,
            onSuccess: onDone,
        });
    }

    return (
        <form onSubmit={submit} noValidate>
            <DialogHeader>
                <DialogTitle>Isi pesan: {kind.title}</DialogTitle>
                <DialogDescription>{kind.description}</DialogDescription>
            </DialogHeader>

            <Field className="my-5" data-invalid={!!form.errors.template}>
                <FieldLabel htmlFor="wa-template">Teks pesan</FieldLabel>
                <Textarea
                    id="wa-template"
                    rows={5}
                    value={form.data.template}
                    onChange={(event) =>
                        form.setData('template', event.target.value)
                    }
                    aria-invalid={!!form.errors.template}
                />
                {form.errors.template ? (
                    <FieldError>{form.errors.template}</FieldError>
                ) : (
                    <FieldDescription>
                        Variabel:{' '}
                        {Object.keys(kind.sample).map((variable) => (
                            <code
                                key={variable}
                                className="mr-1 font-mono text-xs"
                            >
                                {`{${variable}}`}
                            </code>
                        ))}
                    </FieldDescription>
                )}
            </Field>

            <div className="mb-5 border border-border bg-muted p-3 text-sm">
                <p className="mb-1 text-xs font-medium text-muted-foreground">
                    Pratinjau
                </p>
                {fillTemplate(
                    form.data.template === ''
                        ? kind.defaultTemplate
                        : form.data.template,
                    kind.sample,
                )}
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    className="sm:mr-auto"
                    onClick={() =>
                        form.setData('template', kind.defaultTemplate)
                    }
                    disabled={form.data.template === kind.defaultTemplate}
                >
                    Kembalikan ke bawaan
                </Button>
                <Button type="button" variant="outline" onClick={onDone}>
                    Batal
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Simpan
                </Button>
            </DialogFooter>
        </form>
    );
}
