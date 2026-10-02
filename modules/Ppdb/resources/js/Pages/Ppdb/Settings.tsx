import { router, useForm, usePage } from '@inertiajs/react';
import { CheckIcon, CopyIcon } from 'lucide-react';
import { useState } from 'react';

import {
    destroyPath,
    destroyWave,
    storePeriod,
    storeWave,
    updateForm,
    updatePaths,
    updatePeriod,
    updateWave,
} from '@/actions/Modules/Ppdb/App/Http/Controllers/SettingsController';
import { settings as settingsRoute } from '@/routes/ppdb';
import { DataTable, EmptyState, OptionSelect, Panel } from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Field, FieldDescription, FieldError, FieldLabel } from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../Components/ConfirmAction';
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

interface FormField {
    key: string;
    label: string;
    value: string;
}

interface SettingsProps {
    periods: Period[];
    selected: Period | null;
    waves: Wave[];
    paths: Path[];
    statuses: { value: string; label: string }[];
    formFields: FormField[];
    requirements: { value: string; label: string }[];
    fixedFields: string[];
    school: { name: string; code: string; joinUrl: string };
}

type FormErrors = Partial<Record<string, string>>;

/** A read-only value with a button that copies it. */
function CopyField({ id, label, value, hint }: { id: string; label: string; value: string; hint?: string }) {
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
        <Field>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <div className="flex gap-2">
                <Input id={id} readOnly value={value} className="font-mono text-xs" onFocus={(event) => event.target.select()} />
                <Button type="button" variant="outline" onClick={copy} aria-label={`Salin ${label.toLowerCase()}`}>
                    {copied ? <CheckIcon /> : <CopyIcon />}
                    {copied ? 'Tersalin' : 'Salin'}
                </Button>
            </div>
            {hint !== undefined && <FieldDescription>{hint}</FieldDescription>}
        </Field>
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
                <FieldLabel htmlFor={`period-name-${method}`}>Nama periode</FieldLabel>
                <Input
                    id={`period-name-${method}`}
                    value={form.data.name}
                    onChange={(event) => form.setData('name', event.target.value)}
                    aria-invalid={errors.name !== undefined}
                />
                {errors.name !== undefined && <FieldError>{errors.name}</FieldError>}
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field data-invalid={errors.entry_year !== undefined}>
                    <FieldLabel htmlFor={`period-year-${method}`}>Tahun masuk siswa baru</FieldLabel>
                    <Input
                        id={`period-year-${method}`}
                        type="number"
                        inputMode="numeric"
                        value={form.data.entry_year}
                        onChange={(event) => form.setData('entry_year', event.target.value)}
                        aria-invalid={errors.entry_year !== undefined}
                    />
                    {errors.entry_year !== undefined && <FieldError>{errors.entry_year}</FieldError>}
                </Field>
                <Field data-invalid={errors.status !== undefined}>
                    <FieldLabel>Status</FieldLabel>
                    <OptionSelect
                        label="Status periode"
                        value={form.data.status}
                        onChange={(value) => form.setData('status', value)}
                        options={statuses}
                    />
                    <FieldDescription>Hanya satu periode yang berjalan; menjalankan periode ini menutup yang lain.</FieldDescription>
                    {errors.status !== undefined && <FieldError>{errors.status}</FieldError>}
                </Field>
            </div>
            <div>
                <Button type="submit" disabled={form.processing || (method === 'put' && !form.isDirty)}>
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
                    onChange={(event) => form.setData('name', event.target.value)}
                    aria-invalid={errors.name !== undefined}
                />
                {errors.name !== undefined && <FieldError>{errors.name}</FieldError>}
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field data-invalid={errors.opens_on !== undefined}>
                    <FieldLabel htmlFor="wave-opens">Dibuka</FieldLabel>
                    <Input
                        id="wave-opens"
                        type="date"
                        value={form.data.opens_on}
                        onChange={(event) => form.setData('opens_on', event.target.value)}
                        aria-invalid={errors.opens_on !== undefined}
                    />
                    {errors.opens_on !== undefined && <FieldError>{errors.opens_on}</FieldError>}
                </Field>
                <Field data-invalid={errors.closes_on !== undefined}>
                    <FieldLabel htmlFor="wave-closes">Ditutup</FieldLabel>
                    <Input
                        id="wave-closes"
                        type="date"
                        value={form.data.closes_on}
                        onChange={(event) => form.setData('closes_on', event.target.value)}
                        aria-invalid={errors.closes_on !== undefined}
                    />
                    {errors.closes_on !== undefined && <FieldError>{errors.closes_on}</FieldError>}
                </Field>
            </div>
            <div className="flex gap-3">
                <Button type="submit" disabled={form.processing}>
                    {wave === null ? 'Tambah gelombang' : 'Simpan gelombang'}
                </Button>
                {wave !== null && (
                    <Button type="button" variant="outline" onClick={onDone}>
                        Batal
                    </Button>
                )}
            </div>
        </form>
    );
}

/** Paths of the period with the number of seats each offers. */
function PathsForm({ periodId, paths }: { periodId: number; paths: Path[] }) {
    const form = useForm({
        paths: paths.map((path) => ({ id: path.id as number | null, name: path.name, quota: String(path.quota) })),
    });
    const errors: FormErrors = form.errors;

    function change(index: number, field: 'name' | 'quota', value: string) {
        form.setData(
            'paths',
            form.data.paths.map((row, position) => (position === index ? { ...row, [field]: value } : row)),
        );
    }

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(updatePaths.url({ period: periodId }), {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                });
            }}
        >
            {errors.paths !== undefined && <FieldError>{errors.paths}</FieldError>}
            <div className="flex flex-col gap-3">
                {form.data.paths.map((row, index) => {
                    const nameError = errors[`paths.${index}.name`];
                    const quotaError = errors[`paths.${index}.quota`] ?? errors[`paths.${index}.id`];

                    return (
                        <div key={row.id ?? `new-${index}`} className="grid grid-cols-[1fr_7rem_auto] items-start gap-3">
                            <Field data-invalid={nameError !== undefined}>
                                <Input
                                    aria-label={`Nama jalur ${index + 1}`}
                                    value={row.name}
                                    onChange={(event) => change(index, 'name', event.target.value)}
                                    aria-invalid={nameError !== undefined}
                                />
                                {nameError !== undefined && <FieldError>{nameError}</FieldError>}
                            </Field>
                            <Field data-invalid={quotaError !== undefined}>
                                <Input
                                    aria-label={`Kuota jalur ${index + 1}`}
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    value={row.quota}
                                    onChange={(event) => change(index, 'quota', event.target.value)}
                                    aria-invalid={quotaError !== undefined}
                                />
                                {quotaError !== undefined && <FieldError>{quotaError}</FieldError>}
                            </Field>
                            {row.id === null ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() =>
                                        form.setData(
                                            'paths',
                                            form.data.paths.filter((_, position) => position !== index),
                                        )
                                    }
                                >
                                    Hapus
                                </Button>
                            ) : (
                                <ConfirmAction
                                    trigger={
                                        <Button type="button" variant="ghost">
                                            Hapus
                                        </Button>
                                    }
                                    title={`Hapus jalur ${row.name}?`}
                                    description="Jalur dihapus dari periode ini. Jalur yang sudah punya pendaftar tidak bisa dihapus."
                                    confirmLabel="Hapus jalur"
                                    onConfirm={() => router.delete(destroyPath.url({ path: row.id as number }), { preserveScroll: true })}
                                />
                            )}
                        </div>
                    );
                })}
            </div>
            <div className="flex gap-3">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => form.setData('paths', [...form.data.paths, { id: null, name: '', quota: '0' }])}
                >
                    Tambah jalur
                </Button>
                <Button type="submit" disabled={form.processing || !form.isDirty}>
                    Simpan jalur dan kuota
                </Button>
            </div>
        </form>
    );
}

/** Which fields of the registration form the period asks: required, optional or not used. */
function FormFieldsForm({
    periodId,
    fields,
    requirements,
    fixed,
}: {
    periodId: number;
    fields: FormField[];
    requirements: { value: string; label: string }[];
    fixed: string[];
}) {
    const form = useForm({
        fields: Object.fromEntries(fields.map((field) => [field.key, field.value])) as Record<string, string>,
    });
    const errors: FormErrors = form.errors;

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.put(updateForm.url({ period: periodId }), {
                    preserveScroll: true,
                    onSuccess: () => form.setDefaults(),
                });
            }}
        >
            <p className="text-sm text-muted-foreground">
                Pilih isian yang diminta formulir periode ini. {fixed.join(', ')} selalu diminta. Mematikan sebuah isian tidak menghapus
                jawaban pendaftar yang sudah masuk.
            </p>
            <div className="flex flex-col gap-3">
                {fields.map((field) => (
                    <Field key={field.key} data-invalid={errors[`fields.${field.key}`] !== undefined} className="sm:grid sm:grid-cols-[1fr_12rem] sm:items-center">
                        <FieldLabel>{field.label}</FieldLabel>
                        <OptionSelect
                            label={field.label}
                            value={form.data.fields[field.key]}
                            onChange={(value) => form.setData('fields', { ...form.data.fields, [field.key]: value })}
                            options={requirements}
                        />
                        {errors[`fields.${field.key}`] !== undefined && <FieldError>{errors[`fields.${field.key}`]}</FieldError>}
                    </Field>
                ))}
            </div>
            <div>
                <Button type="submit" disabled={form.processing || !form.isDirty}>
                    Simpan formulir
                </Button>
            </div>
        </form>
    );
}

/**
 * Pengaturan PPDB: the school's code and link for applicants, its periods,
 * and the waves, paths and registration form of the chosen period.
 */
export default function Settings({ periods, selected, waves, paths, statuses, formFields, requirements, fixedFields, school }: SettingsProps) {
    const [creating, setCreating] = useState(selected === null);
    const [editingWave, setEditingWave] = useState<Wave | null>(null);
    const [addingWave, setAddingWave] = useState(false);

    const suggestedYear = new Date().getFullYear() + 1;
    const errors = usePage<{ errors: Record<string, string> }>().props.errors;
    const refusal = errors.wave ?? errors.path;

    return (
        <PpdbPage
            title="Pengaturan PPDB"
            description="Periode, gelombang, jalur dan kuota, isian formulir, serta tautan pendaftaran sekolah."
            width="max-w-3xl"
        >
            <div className="flex flex-col gap-6">
                {refusal !== undefined && (
                    <Alert variant="destructive">
                        <AlertDescription>{refusal}</AlertDescription>
                    </Alert>
                )}

                <Panel title="Kode dan tautan sekolah">
                    <div className="flex flex-col gap-4">
                        <p className="text-sm text-muted-foreground">
                            Bagikan tautan ini kepada calon siswa. Mereka membuat akun, lalu bergabung ke {school.name}{' '}
                            dengan kode sekolah.
                        </p>
                        <CopyField id="school-link" label="Tautan pendaftaran" value={school.joinUrl} />
                        <CopyField
                            id="school-code"
                            label="Kode sekolah"
                            value={school.code}
                            hint="Kode yang sama dipakai saat masuk ke SIMAS."
                        />
                    </div>
                </Panel>

                <Panel
                    title="Periode"
                    actions={
                        selected !== null && !creating ? (
                            <Button type="button" variant="outline" size="sm" onClick={() => setCreating(true)}>
                                Periode baru
                            </Button>
                        ) : undefined
                    }
                >
                    {creating ? (
                        <div className="flex flex-col gap-4">
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
                                onDone={() => setCreating(false)}
                            />
                            {selected !== null && (
                                <div>
                                    <Button type="button" variant="ghost" onClick={() => setCreating(false)}>
                                        Batal
                                    </Button>
                                </div>
                            )}
                        </div>
                    ) : (
                        selected !== null && (
                            <div className="flex flex-col gap-6">
                                {periods.length > 1 && (
                                    <Field>
                                        <FieldLabel>Periode yang diatur</FieldLabel>
                                        <OptionSelect
                                            label="Periode yang diatur"
                                            value={String(selected.id)}
                                            onChange={(value) =>
                                                router.get(settingsRoute.url({ query: { periode: value } }), {}, { preserveScroll: true })
                                            }
                                            options={periods.map((period) => ({
                                                value: String(period.id),
                                                label: `${period.name} · ${period.statusLabel}`,
                                            }))}
                                        />
                                    </Field>
                                )}
                                <PeriodForm
                                    key={`period-${selected.id}-${selected.name}-${selected.entryYear}-${selected.status}`}
                                    initial={selected}
                                    statuses={statuses}
                                    url={updatePeriod.url({ period: selected.id })}
                                    method="put"
                                    submitLabel="Simpan periode"
                                />
                            </div>
                        )
                    )}
                </Panel>

                {selected === null ? (
                    <EmptyState>Buat periode PPDB lebih dulu untuk mengatur gelombang, jalur, dan kuota.</EmptyState>
                ) : (
                    <>
                        <Panel
                            title="Gelombang pendaftaran"
                            actions={
                                !addingWave && editingWave === null ? (
                                    <Button type="button" variant="outline" size="sm" onClick={() => setAddingWave(true)}>
                                        Tambah gelombang
                                    </Button>
                                ) : undefined
                            }
                        >
                            <div className="flex flex-col gap-6">
                                {waves.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada gelombang. Calon siswa baru bisa mendaftar saat sebuah gelombang dibuka.
                                    </p>
                                ) : (
                                    <DataTable head={['Gelombang', 'Dibuka', 'Ditutup', 'Status', '']}>
                                        {waves.map((wave) => {
                                            const status = statusOf(wave.status);

                                            return (
                                                <TableRow key={wave.id}>
                                                    <TableCell className="font-medium">{wave.name}</TableCell>
                                                    <TableCell>{wave.opensLabel}</TableCell>
                                                    <TableCell>{wave.closesLabel}</TableCell>
                                                    <TableCell>
                                                        <Badge variant={status.variant}>{status.label}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => {
                                                                setAddingWave(false);
                                                                setEditingWave(wave);
                                                            }}
                                                        >
                                                            Ubah
                                                        </Button>
                                                        <ConfirmAction
                                                            trigger={
                                                                <Button type="button" variant="ghost" size="sm">
                                                                    Hapus
                                                                </Button>
                                                            }
                                                            title={`Hapus ${wave.name}?`}
                                                            description="Gelombang dihapus dari periode ini. Gelombang yang sudah punya pendaftar tidak bisa dihapus."
                                                            confirmLabel="Hapus gelombang"
                                                            onConfirm={() => router.delete(destroyWave.url({ wave: wave.id }), { preserveScroll: true })}
                                                        />
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </DataTable>
                                )}

                                {(addingWave || editingWave !== null) && (
                                    <WaveForm
                                        key={editingWave?.id ?? 'new-wave'}
                                        periodId={selected.id}
                                        wave={editingWave}
                                        onDone={() => {
                                            setAddingWave(false);
                                            setEditingWave(null);
                                        }}
                                    />
                                )}
                            </div>
                        </Panel>

                        <Panel title="Jalur dan kuota">
                            <PathsForm
                                key={`paths-${selected.id}-${paths.map((path) => `${path.id}:${path.name}:${path.quota}`).join('|')}`}
                                periodId={selected.id}
                                paths={paths}
                            />
                        </Panel>

                        <Panel title="Formulir pendaftaran">
                            <FormFieldsForm
                                key={`form-${selected.id}-${formFields.map((field) => `${field.key}:${field.value}`).join('|')}`}
                                periodId={selected.id}
                                fields={formFields}
                                requirements={requirements}
                                fixed={fixedFields}
                            />
                        </Panel>
                    </>
                )}
            </div>
        </PpdbPage>
    );
}
