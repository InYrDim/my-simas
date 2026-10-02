import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    approve as approveInstance,
    disable as disableInstance,
    enable as enableInstance,
    reject as rejectInstance,
    updateSettings,
} from '@/actions/Modules/Platform/App/Http/Controllers/WhatsappInstanceController';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
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
import { TableCell, TableRow } from '@shared/components/ui/table';
import { Textarea } from '@shared/components/ui/textarea';

import { consolePath } from '../../../Components/consolePath';
import {
    DataTable,
    EmptyState,
    PageHeader,
    Panel,
    StatusChip,
} from '../../../Components/ConsoleParts';
import { formatDate } from '../../../Components/format';
import ProviderLayout from '../../../Components/ProviderLayout';
import { send } from '../../../Components/send';
import type { ConsoleWhatsappInstance } from '../../../types/console';

interface WhatsappProps {
    instances: ConsoleWhatsappInstance[];
    autoApprove: boolean;
    /** Gateway address, admin key and credentials key are all set. */
    gatewayReady: boolean;
}

const connectionLabel: Record<string, string> = {
    created: 'belum dihubungkan',
    initializing: 'memulai',
    qr_ready: 'menunggu pindai QR',
    authenticating: 'menautkan',
    ready: 'terhubung',
    action_required: 'perlu tindakan',
    disconnected: 'terputus',
    failed: 'gagal',
};

const schoolName = (instance: ConsoleWhatsappInstance) =>
    instance.tenantName ?? instance.tenantId;

/**
 * Provider console: the schools' WhatsApp requests. Approving registers
 * the school's session on the gateway; rejecting sends a note back to the
 * school. An approved school can be switched off (its session stops, the
 * linked device is kept) and on again. The session key is never part of
 * this page.
 */
export default function WhatsappIndex({
    instances,
    autoApprove,
    gatewayReady,
}: WhatsappProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [noting, setNoting] = useState<{
        instance: ConsoleWhatsappInstance;
        action: NoteAction;
    } | null>(null);

    const pending = instances.filter((instance) => instance.status === 'pending');
    const decided = instances.filter((instance) => instance.status !== 'pending');

    return (
        <ProviderLayout>
            <Head title="WhatsApp" />

            <PageHeader
                title="WhatsApp"
                description="Pengajuan WhatsApp dari sekolah dan sesi tiap sekolah di gateway."
            />

            {!gatewayReady && (
                <Alert variant="destructive" className="mt-6">
                    <AlertDescription>
                        Gateway belum dikonfigurasi. Isi OPENWA_API_BASE_URL,
                        OPENWA_ADMIN_API_KEY, dan OPENWA_CREDENTIALS_KEY di
                        .env sebelum menyetujui pengajuan.
                    </AlertDescription>
                </Alert>
            )}

            {errors?.whatsapp !== undefined && (
                <Alert variant="destructive" className="mt-6">
                    <AlertDescription>{errors.whatsapp}</AlertDescription>
                </Alert>
            )}

            <Panel title="Persetujuan" className="mt-8">
                <Field orientation="horizontal">
                    <Checkbox
                        id="whatsapp-auto-approve"
                        checked={autoApprove}
                        onCheckedChange={(checked) =>
                            send('put', updateSettings.url(), {
                                auto_approve: checked === true,
                            })
                        }
                    />
                    <div className="flex-1">
                        <FieldLabel htmlFor="whatsapp-auto-approve">
                            Setujui pengajuan baru secara otomatis
                        </FieldLabel>
                        <FieldDescription>
                            Sekolah langsung bisa menghubungkan nomornya tanpa
                            menunggu keputusan Anda. Pengajuan yang sudah
                            menunggu tetap perlu disetujui di bawah.
                        </FieldDescription>
                    </div>
                </Field>
            </Panel>

            <h2 className="mt-10 mb-3 text-sm font-semibold">
                Menunggu persetujuan
            </h2>
            {pending.length === 0 ? (
                <EmptyState>Tidak ada pengajuan yang menunggu.</EmptyState>
            ) : (
                <ul className="flex flex-col gap-3">
                    {pending.map((instance) => (
                        <li
                            key={instance.id}
                            className="bg-card px-5 py-4 shadow-sm"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <p className="font-medium">
                                        {schoolName(instance)}
                                    </p>
                                    <p className="mt-0.5 text-sm text-muted-foreground">
                                        {instance.requestedAt !== null
                                            ? `Diajukan ${formatDate(instance.requestedAt)}`
                                            : 'Diajukan'}
                                        {instance.note !== null &&
                                            ` · pernah ditolak: ${instance.note}`}
                                    </p>
                                    {instance.lastError !== null && (
                                        <p className="mt-1 text-sm text-destructive">
                                            Persetujuan terakhir gagal:{' '}
                                            {instance.lastError}
                                        </p>
                                    )}
                                </div>
                                <div className="flex gap-3">
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            setNoting({ instance, action: 'reject' })
                                        }
                                    >
                                        Tolak
                                    </Button>
                                    <Button
                                        onClick={() =>
                                            send(
                                                'post',
                                                approveInstance.url({
                                                    instance: instance.id,
                                                }),
                                            )
                                        }
                                    >
                                        Setujui
                                    </Button>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            <h2 className="mt-10 mb-3 text-sm font-semibold">
                Sudah diputuskan
            </h2>
            {decided.length === 0 ? (
                <EmptyState>Belum ada sekolah yang diputuskan.</EmptyState>
            ) : (
                <DataTable
                    head={['Sekolah', 'Status', 'Sambungan', 'Diputuskan', 'Catatan', '']}
                >
                    {decided.map((instance) => (
                        <TableRow key={instance.id}>
                            <TableCell className="font-medium">
                                {schoolName(instance)}
                            </TableCell>
                            <TableCell>
                                <StatusChip status={instance.status} />
                            </TableCell>
                            <TableCell>
                                {instance.status !== 'active' ||
                                instance.connection === null
                                    ? '—'
                                    : instance.connection === 'ready' &&
                                        instance.phone !== null
                                      ? `terhubung · ${instance.phone}`
                                      : (connectionLabel[instance.connection] ??
                                        instance.connection)}
                            </TableCell>
                            <TableCell>
                                {instance.decidedAt !== null
                                    ? formatDate(instance.decidedAt)
                                    : '—'}
                            </TableCell>
                            <TableCell className="text-muted-foreground">
                                {instance.note ?? '—'}
                                {instance.lastError !== null && (
                                    <span className="block text-destructive">
                                        {instance.lastError}
                                    </span>
                                )}
                            </TableCell>
                            <TableCell className="text-right">
                                {instance.status === 'active' && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            setNoting({ instance, action: 'disable' })
                                        }
                                    >
                                        Nonaktifkan
                                    </Button>
                                )}
                                {instance.status === 'disabled' && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            send(
                                                'post',
                                                enableInstance.url({
                                                    instance: instance.id,
                                                }),
                                            )
                                        }
                                    >
                                        Aktifkan
                                    </Button>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}

            <Dialog
                open={noting !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setNoting(null);
                    }
                }}
            >
                <DialogContent>
                    {noting !== null && (
                        <NoteForm
                            key={`${noting.action}-${noting.instance.id}`}
                            instance={noting.instance}
                            action={noting.action}
                            onDone={() => setNoting(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </ProviderLayout>
    );
}

type NoteAction = 'reject' | 'disable';

const noteActions = {
    reject: {
        route: rejectInstance,
        title: (school: string) => `Tolak pengajuan ${school}?`,
        description: 'Sekolah melihat alasan ini dan bisa mengajukan lagi.',
        submit: 'Tolak pengajuan',
    },
    disable: {
        route: disableInstance,
        title: (school: string) => `Nonaktifkan WhatsApp ${school}?`,
        description:
            'Sesinya dihentikan dan sekolah tidak bisa mengirim pesan. Sekolah melihat alasan ini. Perangkat yang tertaut tetap tersimpan.',
        submit: 'Nonaktifkan',
    },
};

/** A decision against a school that carries a note: reject or disable. */
function NoteForm({
    instance,
    action,
    onDone,
}: {
    instance: ConsoleWhatsappInstance;
    action: NoteAction;
    onDone: () => void;
}) {
    const form = useForm({ note: '' });
    const copy = noteActions[action];

    function submit(event: FormEvent) {
        event.preventDefault();

        // An error that belongs to no field (the gateway refused) shows on
        // the page, so the dialog closes either way.
        form.post(consolePath(copy.route.url({ instance: instance.id })), {
            preserveScroll: true,
            onSuccess: onDone,
            onError: (errors) => {
                if (errors.note === undefined) {
                    onDone();
                }
            },
        });
    }

    return (
        <form onSubmit={submit} noValidate>
            <DialogHeader>
                <DialogTitle>{copy.title(schoolName(instance))}</DialogTitle>
                <DialogDescription>{copy.description}</DialogDescription>
            </DialogHeader>

            <Field className="my-5" data-invalid={!!form.errors.note}>
                <FieldLabel htmlFor="whatsapp-reject-note">
                    Alasan (opsional)
                </FieldLabel>
                <Textarea
                    id="whatsapp-reject-note"
                    rows={3}
                    value={form.data.note}
                    onChange={(event) => form.setData('note', event.target.value)}
                    aria-invalid={!!form.errors.note}
                    autoFocus
                />
                <FieldError>{form.errors.note}</FieldError>
            </Field>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    Batal
                </Button>
                <Button
                    type="submit"
                    variant="destructive"
                    disabled={form.processing}
                >
                    {copy.submit}
                </Button>
            </DialogFooter>
        </form>
    );
}
