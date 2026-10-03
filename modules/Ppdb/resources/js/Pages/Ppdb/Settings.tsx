import { router, useForm, usePage } from '@inertiajs/react';
import { CheckIcon, CopyIcon } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';

import {
    destroyPath,
    destroyWave,
    storePeriod,
    storeWave,
    updatePaths,
    updatePeriod,
    updateWave,
} from '@/actions/Modules/Ppdb/App/Http/Controllers/SettingsController';
import { settings as settingsRoute } from '@/routes/ppdb';
import {
    DataTable,
    EmptyState,
    OptionSelect,
    Panel,
} from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../Components/ConfirmAction';
import FormModal from '../../Components/FormModal';
import PpdbPage from '../../Components/PpdbPage';
import { statusOf } from '../../Components/status';

interface Period {
    id: number;
    name: string;
    entryYear: number;
    status: string;
    statusLabel: string;
    resultsPublished: boolean;
}

interface Wave {
    id: number;
    name: string;
    opensOn: string;
    closesOn: string;
    opensLabel: string;
    closesLabel: string;
    status: string;
}

interface Path {
    id: number;
    name: string;
    quota: number;
}

interface SettingsProps {
    periods: Period[];
    selected: Period | null;
    waves: Wave[];
    paths: Path[];
    statuses: { value: string; label: string }[];
    school: { name: string; code: string; joinUrl: string };
}

type FormErrors = Partial<Record<string, string>>;

/** A read-only value as plain text, with a button that copies it. */
function CopyRow({
    label,
    value,
    hint,
}: {
    label: string;
    value: string;
    hint?: string;
}) {
    const [copied, setCopied] = useState(false);

    async function copy() {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    }

    return (
        <div className="flex items-center justify-between gap-4 border-b border-border py-3 last:border-b-0">
            <div className="min-w-0">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="font-mono text-xs break-all text-foreground">
                    {value}
                </p>
                {hint !== undefined && (
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        {hint}
                    </p>
                )}
            </div>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="shrink-0"
                onClick={copy}
                aria-label={`Salin ${label.toLowerCase()}`}
            >
                {copied ? <CheckIcon /> : <CopyIcon />}
                {copied ? 'Tersalin' : 'Salin'}
            </Button>
        </div>
    );
}

/** Create or edit one period: name, year the students start, and status. */
function PeriodForm({
    initial,
    statuses,
    url,
    method,
    submitLabel,
    onDone,
}: {
    initial: { name: string; entryYear: number; status: string };
    statuses: { value: string; label: string }[];
    url: string;
    method: 'post' | 'put';
    submitLabel: string;
    onDone?: () => void;
}) {
    const form = useForm({
        name: initial.name,
        entry_year: String(initial.entryYear),
        status: initial.status,
    });
    const errors: FormErrors = form.errors;

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form[method](url, {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.setDefaults();
                        onDone?.();
                    },
                });
            }}
        >
            <Field data-invalid={errors.name !== undefined}>
                <FieldLabel htmlFor={`period-name-${method}`}>
                    Nama periode
                </FieldLabel>
                <Input
                    id={`period-name-${method}`}
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    aria-invalid={errors.name !== undefined}
                />
                {errors.name !== undefined && (
                    <FieldError>{errors.name}</FieldError>
                )}
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field data-invalid={errors.entry_year !== undefined}>
                    <FieldLabel htmlFor={`period-year-${method}`}>
                        Tahun masuk siswa baru
                    </FieldLabel>
                    <Input
                        id={`period-year-${method}`}
                        type="number"
                        inputMode="numeric"
                        value={form.data.entry_year}
                        onChange={(event) =>
                            form.setData('entry_year', event.target.value)
                        }
                        aria-invalid={errors.entry_year !== undefined}
                    />
                    {errors.entry_year !== undefined && (
                        <FieldError>{errors.entry_year}</FieldError>
                    )}
                </Field>
                <Field data-invalid={errors.status !== undefined}>
                    <FieldLabel>Status</FieldLabel>
                    <OptionSelect
                        label="Status periode"
                        value={form.data.status}
                        onChange={(value) => form.setData('status', value)}
                        options={statuses}
                    />
                    <FieldDescription>
                        Hanya satu periode yang berjalan; menjalankan periode
                        ini menutup yang lain.
                    </FieldDescription>
                    {errors.status !== undefined && (
                        <FieldError>{errors.status}</FieldError>
                    )}
                </Field>
            </div>
            <div>
                <Button
                    type="submit"
                    disabled={
                        form.processing || (method === 'put' && !form.isDirty)
                    }
                >
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

/** Create or edit one wave: name and the first and last day to register. */
function WaveForm({
    periodId,
    wave,
    onDone,
}: {
    periodId: number;
    wave: Wave | null;
    onDone: () => void;
}) {
    const form = useForm({
        name: wave?.name ?? '',
        opens_on: wave?.opensOn ?? '',
        closes_on: wave?.closesOn ?? '',
    });
    const errors: FormErrors = form.errors;

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();

                const options = {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onDone();
                    },
                };

                if (wave === null) {
                    form.post(storeWave.url({ period: periodId }), options);
                } else {
                    form.put(updateWave.url({ wave: wave.id }), options);
                }
            }}
        >
            <Field data-invalid={errors.name !== undefined}>
                <FieldLabel htmlFor="wave-name">Nama gelombang</FieldLabel>
                <Input
                    id="wave-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    aria-invalid={errors.name !== undefined}
                />
                {errors.name !== undefined && (
                    <FieldError>{errors.name}</FieldError>
                )}
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field data-invalid={errors.opens_on !== undefined}>
                    <FieldLabel htmlFor="wave-opens">Dibuka</FieldLabel>
                    <Input
                        id="wave-opens"
                        type="date"
                        value={form.data.opens_on}
                        onChange={(event) =>
                            form.setData('opens_on', event.target.value)
                        }
                        aria-invalid={errors.opens_on !== undefined}
                    />
                    {errors.opens_on !== undefined && (
                        <FieldError>{errors.opens_on}</FieldError>
                    )}
                </Field>
                <Field data-invalid={errors.closes_on !== undefined}>
                    <FieldLabel htmlFor="wave-closes">Ditutup</FieldLabel>
                    <Input
                        id="wave-closes"
                        type="date"
                        value={form.data.closes_on}
                        onChange={(event) =>
                            form.setData('closes_on', event.target.value)
                        }
                        aria-invalid={errors.closes_on !== undefined}
                    />
                    {errors.closes_on !== undefined && (
                        <FieldError>{errors.closes_on}</FieldError>
                    )}
                </Field>
            </div>
            <div className="flex gap-3">
                <Button type="submit" disabled={form.processing}>
                    Simpan gelombang
                </Button>
                <Button type="button" variant="outline" onClick={onDone}>
                    Batal
                </Button>
            </div>
        </form>
    );
}

/**
 * Edit the paths of the period and the seats each offers, and add new ones.
 * Deleting a saved path is done from the table on the page, not here.
 */
function PathsForm({
    periodId,
    paths,
    onDone,
}: {
    periodId: number;
    paths: Path[];
    onDone: () => void;
}) {
    const form = useForm({
        paths: paths.map((path) => ({
            id: path.id as number | null,
            name: path.name,
            quota: String(path.quota),
        })),
    });
    const errors: FormErrors = form.errors;

    function change(index: number, field: 'name' | 'quota', value: string) {
        form.setData(
            'paths',
            form.data.paths.map((row, position) =>
                position === index ? { ...row, [field]: value } : row,
            ),
        );
    }

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(updatePaths.url({ period: periodId }), {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.setDefaults();
                        onDone();
                    },
                });
            }}
        >
            {errors.paths !== undefined && (
                <FieldError>{errors.paths}</FieldError>
            )}
            <div className="flex flex-col gap-3">
                {form.data.paths.map((row, index) => {
                    const nameError = errors[`paths.${index}.name`];
                    const quotaError =
                        errors[`paths.${index}.quota`] ??
                        errors[`paths.${index}.id`];

                    return (
                        <div
                            key={row.id ?? `new-${index}`}
                            className="grid grid-cols-[1fr_7rem_auto] items-start gap-3"
                        >
                            <Field data-invalid={nameError !== undefined}>
                                <Input
                                    aria-label={`Nama jalur ${index + 1}`}
                                    value={row.name}
                                    onChange={(event) =>
                                        change(
                                            index,
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={nameError !== undefined}
                                />
                                {nameError !== undefined && (
                                    <FieldError>{nameError}</FieldError>
                                )}
                            </Field>
                            <Field data-invalid={quotaError !== undefined}>
                                <Input
                                    aria-label={`Kuota jalur ${index + 1}`}
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    value={row.quota}
                                    onChange={(event) =>
                                        change(
                                            index,
                                            'quota',
                                            event.target.value,
                                        )
                                    }
                                    aria-invalid={quotaError !== undefined}
                                />
                                {quotaError !== undefined && (
                                    <FieldError>{quotaError}</FieldError>
                                )}
                            </Field>
                            {row.id === null ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        form.setData(
                                            'paths',
                                            form.data.paths.filter(
                                                (_, position) =>
                                                    position !== index,
                                            ),
                                        )
                                    }
                                >
                                    Buang
                                </Button>
                            ) : (
                                <span aria-hidden="true" />
                            )}
                        </div>
                    );
                })}
            </div>
            <div className="flex gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        form.setData('paths', [
                            ...form.data.paths,
                            { id: null, name: '', quota: '0' },
                        ])
                    }
                >
                    Tambah jalur
                </Button>
                <Button
                    type="submit"
                    disabled={form.processing || !form.isDirty}
                >
                    Simpan jalur dan kuota
                </Button>
            </div>
        </form>
    );
}

/**
 * Pengaturan PPDB: the school's code and link for applicants, its periods,
 * and the waves and paths of the chosen period.
 */
export default function Settings({
    periods,
    selected,
    waves,
    paths,
    statuses,
    school,
}: SettingsProps) {
    const suggestedYear = new Date().getFullYear() + 1;
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;
    const refusal = errors.wave ?? errors.path;
    const totalQuota = paths.reduce((sum, path) => sum + path.quota, 0);

    /** A new period is made in a dialog; `trigger` is the button that opens it. */
    const newPeriod = (trigger: ReactNode) => (
        <FormModal
            trigger={trigger}
            title="Periode baru"
            description="Satu periode berjalan pada satu waktu; menjalankan yang baru menutup yang lama."
        >
            {(close) => (
                <PeriodForm
                    key="new-period"
                    initial={{
                        name: `PPDB ${suggestedYear}/${suggestedYear + 1}`,
                        entryYear: suggestedYear,
                        status: 'draft',
                    }}
                    statuses={statuses}
                    url={storePeriod.url()}
                    method="post"
                    submitLabel="Buat periode"
                    onDone={close}
                />
            )}
        </FormModal>
    );

    return (
        <PpdbPage
            title="Pengaturan PPDB"
            description="Periode, gelombang, jalur dan kuota, serta tautan pendaftaran sekolah."
            width="max-w-3xl"
        >
            <div className="flex flex-col gap-6">
                {refusal !== undefined && (
                    <Alert variant="destructive">
                        <AlertDescription>{refusal}</AlertDescription>
                    </Alert>
                )}

                {selected === null ? (
                    <div className="flex flex-col items-center gap-4">
                        <EmptyState>
                            Belum ada periode PPDB. Buat periode lebih dulu
                            untuk mengatur gelombang, jalur, dan kuota.
                        </EmptyState>
                        {newPeriod(<Button type="button">Buat periode</Button>)}
                    </div>
                ) : (
                    <>
                        <Panel
                            title="Periode"
                            actions={
                                <div className="flex gap-2">
                                    <FormModal
                                        trigger={
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                            >
                                                Ubah periode
                                            </Button>
                                        }
                                        title="Ubah periode"
                                    >
                                        {(close) => (
                                            <PeriodForm
                                                initial={selected}
                                                statuses={statuses}
                                                url={updatePeriod.url({
                                                    period: selected.id,
                                                })}
                                                method="put"
                                                submitLabel="Simpan periode"
                                                onDone={close}
                                            />
                                        )}
                                    </FormModal>
                                    {newPeriod(
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                        >
                                            Periode baru
                                        </Button>,
                                    )}
                                </div>
                            }
                        >
                            <div className="flex flex-col gap-4">
                                {periods.length > 1 && (
                                    <Field>
                                        <FieldLabel>
                                            Periode yang diatur
                                        </FieldLabel>
                                        <OptionSelect
                                            label="Periode yang diatur"
                                            value={String(selected.id)}
                                            onChange={(value) =>
                                                router.get(
                                                    settingsRoute.url({
                                                        query: {
                                                            periode: value,
                                                        },
                                                    }),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                            options={periods.map((period) => ({
                                                value: String(period.id),
                                                label: `${period.name} · ${period.statusLabel}`,
                                            }))}
                                        />
                                    </Field>
                                )}

                                <div>
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <p className="text-sm font-semibold text-foreground">
                                            {selected.name}
                                        </p>
                                        <Badge
                                            variant={
                                                statusOf(selected.status)
                                                    .variant
                                            }
                                        >
                                            {selected.statusLabel}
                                        </Badge>
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Siswa baru masuk tahun{' '}
                                        {selected.entryYear}
                                        {selected.resultsPublished
                                            ? ' · hasil seleksi sudah diumumkan'
                                            : ''}
                                    </p>
                                </div>
                            </div>
                        </Panel>

                        <Panel
                            title="Gelombang pendaftaran"
                            actions={
                                <FormModal
                                    trigger={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                        >
                                            Tambah gelombang
                                        </Button>
                                    }
                                    title="Gelombang baru"
                                    description="Calon siswa baru bisa mendaftar selama sebuah gelombang dibuka."
                                >
                                    {(close) => (
                                        <WaveForm
                                            periodId={selected.id}
                                            wave={null}
                                            onDone={close}
                                        />
                                    )}
                                </FormModal>
                            }
                        >
                            {waves.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Belum ada gelombang. Calon siswa baru bisa
                                    mendaftar saat sebuah gelombang dibuka.
                                </p>
                            ) : (
                                <DataTable
                                    head={[
                                        'Gelombang',
                                        'Dibuka',
                                        'Ditutup',
                                        'Status',
                                        '',
                                    ]}
                                >
                                    {waves.map((wave) => {
                                        const status = statusOf(wave.status);

                                        return (
                                            <TableRow key={wave.id}>
                                                <TableCell className="font-medium">
                                                    {wave.name}
                                                </TableCell>
                                                <TableCell>
                                                    {wave.opensLabel}
                                                </TableCell>
                                                <TableCell>
                                                    {wave.closesLabel}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={status.variant}
                                                    >
                                                        {status.label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <FormModal
                                                        trigger={
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                Ubah
                                                            </Button>
                                                        }
                                                        title={`Ubah ${wave.name}`}
                                                    >
                                                        {(close) => (
                                                            <WaveForm
                                                                periodId={
                                                                    selected.id
                                                                }
                                                                wave={wave}
                                                                onDone={close}
                                                            />
                                                        )}
                                                    </FormModal>
                                                    <ConfirmAction
                                                        trigger={
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="sm"
                                                            >
                                                                Hapus
                                                            </Button>
                                                        }
                                                        title={`Hapus ${wave.name}?`}
                                                        description="Gelombang dihapus dari periode ini. Gelombang yang sudah punya pendaftar tidak bisa dihapus."
                                                        confirmLabel="Hapus gelombang"
                                                        onConfirm={() =>
                                                            router.delete(
                                                                destroyWave.url(
                                                                    {
                                                                        wave: wave.id,
                                                                    },
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </DataTable>
                            )}
                        </Panel>

                        <Panel
                            title="Jalur dan kuota"
                            actions={
                                <FormModal
                                    trigger={
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                        >
                                            Atur jalur dan kuota
                                        </Button>
                                    }
                                    title="Atur jalur dan kuota"
                                    description="Ubah nama dan jumlah kursi tiap jalur, atau tambah jalur baru."
                                >
                                    {(close) => (
                                        <PathsForm
                                            periodId={selected.id}
                                            paths={paths}
                                            onDone={close}
                                        />
                                    )}
                                </FormModal>
                            }
                        >
                            <div className="flex flex-col gap-3">
                                <DataTable head={['Jalur', 'Kuota', '']}>
                                    {paths.map((path) => (
                                        <TableRow key={path.id}>
                                            <TableCell className="font-medium">
                                                {path.name}
                                            </TableCell>
                                            <TableCell>{path.quota}</TableCell>
                                            <TableCell className="text-right">
                                                <ConfirmAction
                                                    trigger={
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            Hapus
                                                        </Button>
                                                    }
                                                    title={`Hapus jalur ${path.name}?`}
                                                    description="Jalur dihapus dari periode ini. Jalur yang sudah punya pendaftar tidak bisa dihapus."
                                                    confirmLabel="Hapus jalur"
                                                    onConfirm={() =>
                                                        router.delete(
                                                            destroyPath.url({
                                                                path: path.id,
                                                            }),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </DataTable>
                                <p className="text-xs text-muted-foreground">
                                    Total {totalQuota} kursi.
                                </p>
                            </div>
                        </Panel>
                    </>
                )}

                <Panel title="Kode dan tautan sekolah">
                    <p className="text-sm text-muted-foreground">
                        Bagikan tautan ini kepada calon siswa. Mereka membuat
                        akun, lalu bergabung ke {school.name} dengan kode
                        sekolah.
                    </p>
                    <div className="mt-2">
                        <CopyRow
                            label="Tautan pendaftaran"
                            value={school.joinUrl}
                        />
                        <CopyRow
                            label="Kode sekolah"
                            value={school.code}
                            hint="Kode yang sama dipakai saat masuk ke SIMAS."
                        />
                    </div>
                </Panel>
            </div>
        </PpdbPage>
    );
}
