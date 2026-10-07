import { router, useForm, usePage, usePoll } from "@inertiajs/react";
import {
    MessageCircleIcon,
    PauseCircleIcon,
    QrCodeIcon,
    ShieldAlertIcon,
} from "lucide-react";
import { useState } from "react";
import type { FormEvent } from "react";

import {
    acknowledge as acknowledgeRisk,
    connect as connectWhatsapp,
    disconnect as disconnectWhatsapp,
    index as whatsappIndex,
    request as requestWhatsapp,
    test as testWhatsapp,
    updateNotice,
} from "@/actions/Modules/Core/App/Http/Controllers/WhatsappController";
import ListPager from "@shared/components/ListPager";
import type { Pagination } from "@shared/components/ListPager";
import { DataTable, EmptyState, Panel } from "@shared/components/page-parts";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@shared/components/ui/alert-dialog";
import {
    Alert,
    AlertDescription,
    AlertTitle,
} from "@shared/components/ui/alert";
import { Badge } from "@shared/components/ui/badge";
import { Button } from "@shared/components/ui/button";
import { Checkbox } from "@shared/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@shared/components/ui/dialog";
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from "@shared/components/ui/field";
import { RadioGroup, RadioGroupItem } from "@shared/components/ui/radio-group";
import { Spinner } from "@shared/components/ui/spinner";
import { Textarea } from "@shared/components/ui/textarea";
import { TableCell, TableRow } from "@shared/components/ui/table";

import ConfirmAction from "../../../../Components/ConfirmAction";
import FormDialog from "../../../../Components/FormDialog";
import { InputField } from "../../../../Components/FormField";
import MasterPage from "../../../../Components/MasterPage";
import type { SchoolSummary } from "../../../../types/master";

/** Where the school's WhatsApp stands. It never carries a gateway key. */
interface WhatsappState {
    stage: "none" | "pending" | "rejected" | "disabled" | "active";
    /** The gateway's session status (`created`, `ready`, ...), only while active. */
    connection: string | null;
    phone: string | null;
    pushName: string | null;
    /** Why the provider rejected or disabled it. */
    note: string | null;
    qrCode: string | null;
    lastError: string | null;
    requestedAt: string | null;
    /** Sending is held back until this moment (ISO 8601). */
    pausedUntil: string | null;
    pauseReason: string | null;
    /** The school has accepted the risks of an unofficial WhatsApp link. */
    riskAcknowledged: boolean;
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
    /** Sends many messages a day, so turning it on asks for confirmation. */
    highVolume: boolean;
    enabled: boolean;
    /** The wording in use: the school's own, or the default. */
    template: string;
    defaultTemplate: string;
    /** Ends the message with a line inviting the recipient to reply. */
    replyFooter: boolean;
    /** The school's own footer wording; null means the default. */
    replyFooterText: string | null;
    defaultFooter: string;
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
    status: "pending" | "sent" | "failed" | "unsent" | "no_recipient";
    error: string | null;
}

type BadgeVariant = "default" | "secondary" | "destructive" | "outline";

const historyStatus: Record<MessageRow["status"], [BadgeVariant, string]> = {
    pending: ["outline", "Dalam antrean"],
    sent: ["default", "Terkirim ke WhatsApp"],
    failed: ["destructive", "Gagal"],
    unsent: ["secondary", "WhatsApp belum terhubung"],
    no_recipient: ["secondary", "Tanpa nomor tujuan"],
};

const dayFormat = new Intl.DateTimeFormat("id-ID", {
    day: "numeric",
    month: "long",
    year: "numeric",
});

const timeFormat = new Intl.DateTimeFormat("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
});

/** Preview of a template: the first choice of every `{a|b|c}` group, then the variables. */
function fillTemplate(body: string, sample: Record<string, string>): string {
    const chosen = body.replace(
        /\{([^{}|]*(?:\|[^{}|]*)+)\}/g,
        (_match, group: string) => group.split("|")[0].trim(),
    );

    return chosen.replace(
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
        case "pending":
            return {
                heading: "Menunggu persetujuan",
                badge: ["outline", "Menunggu"],
                text:
                    state.requestedAt !== null
                        ? `Diajukan ${dayFormat.format(new Date(state.requestedAt))}. Penyedia layanan akan meninjau pengajuan ini.`
                        : "Penyedia layanan akan meninjau pengajuan ini.",
            };
        case "rejected":
            return {
                heading: "Pengajuan ditolak",
                badge: ["destructive", "Ditolak"],
                text:
                    state.note ??
                    "Penyedia layanan menolak pengajuan ini tanpa catatan.",
            };
        case "disabled":
            return {
                heading: "WhatsApp dinonaktifkan",
                badge: ["destructive", "Dinonaktifkan"],
                text:
                    state.note ??
                    "Penyedia layanan menonaktifkan WhatsApp sekolah ini.",
            };
        case "active":
            return describeConnection(state);
        default:
            return {
                heading: "Belum diajukan",
                badge: ["outline", "Belum aktif"],
                text: "Ajukan WhatsApp ke penyedia layanan. Setelah disetujui, nomor WhatsApp sekolah bisa dihubungkan di sini.",
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
        case "ready":
            return {
                heading: state.phone ?? "Terhubung",
                badge: ["default", "Terhubung"],
                text: state.pushName ?? "Nomor WhatsApp sekolah terhubung.",
            };
        case "initializing":
            return {
                heading: "Menyiapkan sambungan",
                badge: ["outline", "Menghubungkan"],
                text: "Kode QR akan muncul di sini dalam beberapa saat.",
            };
        case "qr_ready":
            return {
                heading: "Pindai kode QR",
                badge: ["outline", "Menunggu pindai"],
                text: "Pindai kode di bawah dari WhatsApp di ponsel sekolah. Kode berganti sendiri.",
            };
        case "authenticating":
            return {
                heading: "Menautkan perangkat",
                badge: ["outline", "Menghubungkan"],
                text: "Kode sudah dipindai. Tunggu sampai WhatsApp selesai menautkan.",
            };
        case "failed":
            return {
                heading: "Sambungan gagal",
                badge: ["destructive", "Gagal"],
                text: "WhatsApp tidak berhasil dihubungkan. Coba hubungkan lagi.",
            };
        case "action_required":
            return {
                heading: "Perlu dihubungkan lagi",
                badge: ["destructive", "Perlu tindakan"],
                text: "WhatsApp meminta perangkat ditautkan ulang. Hubungkan lagi untuk memindai kode baru.",
            };
        case "disconnected":
            return {
                heading: "Tidak terhubung",
                badge: ["secondary", "Terputus"],
                text: "Nomor WhatsApp sekolah sedang tidak terhubung.",
            };
        default:
            return {
                heading: "Disetujui",
                badge: ["secondary", "Belum terhubung"],
                text: "WhatsApp sekolah sudah disetujui. Hubungkan nomor WhatsApp sekolah untuk mulai mengirim pesan.",
            };
    }
}

/** Link steps that finish on their own: the page keeps asking meanwhile. */
const linking = ["initializing", "qr_ready", "authenticating"];

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
    const active = state.stage === "active";
    const connected = active && state.connection === "ready";
    const underWay =
        active &&
        state.connection !== null &&
        linking.includes(state.connection);
    const queued = history.some((row) => row.status === "pending");
    const status = describe(state);
    const paused = state.pausedUntil !== null;
    const [requesting, setRequesting] = useState(false);
    const [editing, setEditing] = useState<NoticeKind | null>(null);
    const [askingRisk, setAskingRisk] = useState(false);
    const [confirming, setConfirming] = useState<NoticeKind | null>(null);

    function toggle(kind: NoticeKind, enabled: boolean, confirmed = false) {
        if (enabled && kind.highVolume && !confirmed) {
            setConfirming(kind);

            return;
        }

        router.put(
            updateNotice.url(kind.key),
            {
                enabled,
                template: kind.template,
                reply_footer: kind.replyFooter,
                reply_footer_text: kind.replyFooterText,
                confirm_volume: enabled && kind.highVolume,
            },
            { preserveScroll: true },
        );
    }

    function post(url: string, onSuccess?: () => void) {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onSuccess,
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

                    {(state.stage === "none" || state.stage === "rejected") && (
                        <Button
                            onClick={() => post(requestWhatsapp.url())}
                            disabled={requesting}
                        >
                            {state.stage === "rejected"
                                ? "Ajukan lagi"
                                : "Ajukan WhatsApp"}
                        </Button>
                    )}

                    {active && !connected && !underWay && (
                        <Button
                            onClick={() =>
                                state.riskAcknowledged
                                    ? post(connectWhatsapp.url())
                                    : setAskingRisk(true)
                            }
                            disabled={requesting}
                        >
                            <QrCodeIcon />
                            {state.connection === "failed" ||
                            state.connection === "action_required"
                                ? "Hubungkan lagi"
                                : "Hubungkan"}
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

                {state.pausedUntil !== null && (
                    <Alert className="mt-4">
                        <PauseCircleIcon />
                        <AlertTitle>
                            Pengiriman dijeda sampai{" "}
                            {timeFormat.format(new Date(state.pausedUntil))}
                        </AlertTitle>
                        <AlertDescription>
                            {state.pauseReason ??
                                "Pesan menunggu di antrean dan terkirim otomatis setelah jeda berakhir."}
                        </AlertDescription>
                    </Alert>
                )}
                {paused && <Poll only={["state"]} />}

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
                        <Poll only={["state"]} />
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
                                {state.connection === "authenticating"
                                    ? "Menautkan perangkat…"
                                    : "Menyiapkan kode QR…"}
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
                    {errors?.confirm_volume && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertDescription>
                                {errors.confirm_volume}
                            </AlertDescription>
                        </Alert>
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
                                    {kind.highVolume && (
                                        <Badge variant="outline">
                                            Volume tinggi
                                        </Badge>
                                    )}
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

            <AlertDialog open={askingRisk} onOpenChange={setAskingRisk}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <ShieldAlertIcon className="size-5" aria-hidden />
                            Baca dulu sebelum menghubungkan WhatsApp
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Sambungan ini memakai WhatsApp biasa lewat perangkat
                            tertaut, bukan layanan resmi WhatsApp.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <ul className="list-disc space-y-2 pl-5 text-sm">
                        <li>
                            WhatsApp tidak mengizinkan cara ini secara resmi.
                            Nomor sekolah bisa dibatasi atau diblokir
                            sewaktu-waktu, tanpa pemberitahuan lebih dulu.
                        </li>
                        <li>
                            Pakai nomor khusus sekolah, jangan nomor pribadi
                            atau nomor yang dipakai untuk urusan penting lain.
                        </li>
                        <li>
                            Aplikasi mengatur jeda dan batas kirim harian untuk
                            mengurangi risiko, tetapi tidak bisa meniadakannya.
                        </li>
                        <li>
                            Sekolah bertanggung jawab penuh atas penggunaan
                            nomor dan isi pesan yang dikirim.
                        </li>
                    </ul>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                post(acknowledgeRisk.url(), () =>
                                    post(connectWhatsapp.url()),
                                )
                            }
                        >
                            Saya mengerti dan setuju
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AlertDialog
                open={confirming !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setConfirming(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Nyalakan {confirming?.title}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Pemberitahuan ini dikirim untuk setiap kejadian,
                            sehingga bisa mencapai ratusan pesan sehari. Volume
                            tinggi dari satu nomor menaikkan risiko nomor
                            sekolah dibatasi atau diblokir WhatsApp. Pesan
                            dikirim berjeda dan bisa tertunda; nyalakan hanya
                            bila memang perlu.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => {
                                if (confirming !== null) {
                                    toggle(confirming, true, true);
                                }
                            }}
                        >
                            Ya, nyalakan
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <h2 className="mt-10 mb-3 text-sm font-semibold">Riwayat pesan</h2>
            {queued && <Poll only={["history", "pagination"]} />}
            {history.length === 0 ? (
                <EmptyState>Belum ada pesan yang dikirim.</EmptyState>
            ) : (
                <DataTable
                    head={["Waktu", "Penerima", "Nomor", "Jenis", "Status"]}
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
                                        row.status !== "sent" && (
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
    const form = useForm({
        enabled: kind.enabled,
        template: kind.template,
        reply_footer: kind.replyFooter,
        reply_footer_text: (kind.replyFooterText ?? null) as string | null,
        confirm_volume: kind.enabled && kind.highVolume,
    });
    const [customFooter, setCustomFooter] = useState(
        kind.replyFooterText !== null && kind.replyFooterText !== "",
    );

    function chooseFooter(custom: boolean) {
        setCustomFooter(custom);
        form.setData(
            "reply_footer_text",
            custom ? (kind.replyFooterText ?? "") : null,
        );
    }

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
                        form.setData("template", event.target.value)
                    }
                    aria-invalid={!!form.errors.template}
                />
                {form.errors.template ? (
                    <FieldError>{form.errors.template}</FieldError>
                ) : (
                    <FieldDescription>
                        Variabel:{" "}
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
                <FieldDescription>
                    Variasi: tulis{" "}
                    <code className="font-mono text-xs">
                        {"{halo|hai|selamat pagi}"}
                    </code>{" "}
                    agar tiap pesan memakai salah satu pilihan secara acak,
                    sehingga pesan tidak seragam. Satu grup minimal dua pilihan.
                    Pratinjau di bawah memakai pilihan pertama.
                </FieldDescription>
            </Field>

            <Field
                className="mb-5"
                data-invalid={!!form.errors.reply_footer_text}
            >
                <div className="flex items-start gap-2">
                    <Checkbox
                        id="wa-reply-footer"
                        checked={form.data.reply_footer}
                        onCheckedChange={(checked) =>
                            form.setData("reply_footer", checked === true)
                        }
                    />
                    <FieldLabel htmlFor="wa-reply-footer">
                        Tambahkan ajakan agar penerima membalas pesan ini
                    </FieldLabel>
                </div>
                <FieldDescription>
                    Balasan dari wali membuat nomor sekolah terlihat wajar bagi
                    WhatsApp.
                </FieldDescription>

                {form.data.reply_footer && (
                    <RadioGroup
                        className="mt-2 text-sm"
                        value={customFooter ? "custom" : "default"}
                        onValueChange={(value) =>
                            chooseFooter(value === "custom")
                        }
                    >
                        <label className="flex items-center gap-2">
                            <RadioGroupItem value="default" />
                            Teks bawaan
                        </label>
                        {!customFooter && (
                            <p className="border border-border bg-muted p-2 text-muted-foreground">
                                {kind.defaultFooter}
                            </p>
                        )}
                        <label className="flex items-center gap-2">
                            <RadioGroupItem value="custom" />
                            Teks sendiri
                        </label>
                        {customFooter && (
                            <>
                                <Textarea
                                    id="wa-footer-text"
                                    rows={2}
                                    maxLength={300}
                                    aria-label="Teks ajakan membalas"
                                    value={form.data.reply_footer_text ?? ""}
                                    onChange={(event) =>
                                        form.setData(
                                            "reply_footer_text",
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={
                                        !!form.errors.reply_footer_text
                                    }
                                />
                                <FieldDescription>
                                    Maksimal 300 karakter. Boleh memakai variasi{" "}
                                    <code className="font-mono text-xs">
                                        {"{a|b|c}"}
                                    </code>{" "}
                                    dan variabel seperti{" "}
                                    <code className="font-mono text-xs">
                                        {"{nama_sekolah}"}
                                    </code>
                                    .
                                </FieldDescription>
                            </>
                        )}
                    </RadioGroup>
                )}
                {form.errors.reply_footer_text && (
                    <FieldError>{form.errors.reply_footer_text}</FieldError>
                )}
            </Field>

            <div className="mb-5 border border-border bg-muted p-3 text-sm whitespace-pre-line">
                <p className="mb-1 text-xs font-medium text-muted-foreground">
                    Pratinjau
                </p>
                {fillTemplate(
                    form.data.template === ""
                        ? kind.defaultTemplate
                        : form.data.template,
                    kind.sample,
                )}
                {form.data.reply_footer &&
                    `

${fillTemplate(
    customFooter && (form.data.reply_footer_text ?? "") !== ""
        ? (form.data.reply_footer_text as string)
        : kind.defaultFooter,
    kind.sample,
)}`}
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="ghost"
                    className="sm:mr-auto"
                    onClick={() =>
                        form.setData("template", kind.defaultTemplate)
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
