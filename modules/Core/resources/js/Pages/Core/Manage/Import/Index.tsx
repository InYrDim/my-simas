import { Link, useHttp } from '@inertiajs/react';
import { useState } from 'react';

import {
    preview as previewRoute,
    store,
    template,
} from '@/actions/Modules/Core/App/Http/Controllers/ImportController';
import { index as studentsPage } from '@/actions/Modules/Core/App/Http/Controllers/StudentController';
import { index as teachersPage } from '@/actions/Modules/Core/App/Http/Controllers/TeacherController';
import {
    DataTable,
    OptionSelect,
    Panel,
} from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { Checkbox } from '@shared/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@shared/components/ui/field';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';
import { cn } from '@shared/lib/utils';

import MasterPage from '../../../../Components/MasterPage';
import type { SchoolSummary } from '../../../../types/master';

interface ImportTarget {
    value: string;
    label: string;
    columns: string[];
    required: string[];
}

interface PreviewRow {
    line: number;
    key: string;
    name: string;
    detail: string;
    outcome: 'create' | 'update' | 'error';
    messages: string[];
}

interface Preview {
    summary: { total: number; create: number; update: number; error: number };
    rows: PreviewRow[];
}

interface ImportResult {
    created: number;
    updated: number;
    skipped: number;
}

const steps = ['Unggah berkas', 'Pratinjau', 'Selesai'];

const modes = [
    { value: 'add', label: 'Tambah saja' },
    { value: 'upsert', label: 'Perbarui yang sudah ada' },
];

const modeHints: Record<string, string> = {
    add: 'Baris dengan nomor induk yang sudah terdaftar dilewati; data lama tidak berubah.',
    upsert: 'Baris dengan nomor induk yang sudah terdaftar memperbarui data lama. Sel kosong tidak mengubah apa pun.',
};

const failure = 'Permintaan gagal diproses. Muat ulang halaman lalu coba lagi.';

/**
 * Impor Data: bring students or teachers in from CSV in three steps —
 * upload, preview (the server checks every row), confirm. The file stays
 * in the browser and is sent again to import it.
 */
export default function ImportIndex({
    school,
    targets,
    maxRows,
}: {
    school: SchoolSummary;
    targets: ImportTarget[];
    maxRows: number;
}) {
    const [step, setStep] = useState(0);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [result, setResult] = useState<ImportResult | null>(null);
    const [onlyErrors, setOnlyErrors] = useState(false);
    const [failed, setFailed] = useState(false);
    const http = useHttp<{ target: string; mode: string; file: File | null }>({
        target: targets[0].value,
        mode: 'add',
        file: null,
    });

    const target =
        targets.find((item) => item.value === http.data.target) ?? targets[0];
    const isStudents = target.value === 'students';
    const keyLabel = isStudents ? 'NIS' : 'NIP';
    const ready = preview === null ? 0 : preview.summary.create + preview.summary.update;
    const rows =
        preview?.rows.filter((row) => !onlyErrors || row.outcome === 'error') ?? [];

    const send = <T,>(url: string, done: (response: T) => void) => {
        setFailed(false);
        http.post(url, {
            headers: { Accept: 'application/json' },
            onSuccess: (response) => done(response as T),
            onHttpException: () => setFailed(true),
            onNetworkError: () => setFailed(true),
        }).catch(() => undefined);
    };

    const runPreview = () =>
        send<Preview>(previewRoute.url(), (response) => {
            setPreview(response);
            setOnlyErrors(false);
            setStep(1);
        });

    const runImport = () =>
        send<ImportResult>(store.url(), (response) => {
            setResult(response);
            setStep(2);
        });

    const restart = () => {
        http.reset('file');
        http.clearErrors();
        setPreview(null);
        setResult(null);
        setStep(0);
    };

    return (
        <MasterPage
            school={school}
            title="Impor Data"
            description="Masukkan data siswa atau guru secara massal dari berkas CSV."
            width="max-w-4xl"
            mock={false}
        >
            <ol className="mb-6 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                {steps.map((label, index) => (
                    <li
                        key={label}
                        aria-current={index === step ? 'step' : undefined}
                        className={cn(
                            'flex items-center gap-2',
                            index === step
                                ? 'font-medium text-foreground'
                                : 'text-muted-foreground',
                        )}
                    >
                        <span
                            className={cn(
                                'flex size-6 items-center justify-center rounded-full border text-xs',
                                index <= step && 'border-primary bg-primary text-primary-foreground',
                            )}
                        >
                            {index + 1}
                        </span>
                        {label}
                    </li>
                ))}
            </ol>

            {failed && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{failure}</AlertDescription>
                </Alert>
            )}

            {step === 0 && (
                <Panel title="Unggah berkas">
                    <div className="flex flex-col gap-6">
                        <div className="grid gap-6 sm:grid-cols-2">
                            <Field>
                                <FieldLabel>Jenis data</FieldLabel>
                                <OptionSelect
                                    label="Jenis data"
                                    value={http.data.target}
                                    onChange={(value) => http.setData('target', value)}
                                    options={targets}
                                />
                            </Field>
                            <Field>
                                <FieldLabel>Mode impor</FieldLabel>
                                <OptionSelect
                                    label="Mode impor"
                                    value={http.data.mode}
                                    onChange={(value) => http.setData('mode', value)}
                                    options={modes}
                                />
                                <FieldDescription>
                                    {modeHints[http.data.mode]}
                                </FieldDescription>
                            </Field>
                        </div>
                        <Field data-invalid={http.errors.file !== undefined}>
                            <FieldLabel htmlFor="file">Berkas CSV</FieldLabel>
                            <Input
                                id="file"
                                type="file"
                                accept=".csv,text/csv"
                                aria-invalid={http.errors.file !== undefined}
                                onChange={(event) => {
                                    http.setData('file', event.target.files?.[0] ?? null);
                                    http.clearErrors('file');
                                }}
                            />
                            <FieldDescription>
                                Kolom: {target.columns.join(', ')}. Wajib diisi:{' '}
                                {target.required.join(', ')}. Paling banyak{' '}
                                {maxRows.toLocaleString('id-ID')} baris per berkas.
                                {isStudents
                                    ? ' Kolom kelas diisi nama rombel tahun ajaran aktif.'
                                    : ` Guru tanpa NIP selalu ditambahkan sebagai data baru.`}{' '}
                                Di Excel, format kolom {keyLabel} sebagai Teks
                                supaya angka nol di depan dan digit terakhir
                                tidak hilang.
                            </FieldDescription>
                            {http.errors.file !== undefined && (
                                <FieldError>{http.errors.file}</FieldError>
                            )}
                        </Field>
                        <div className="flex flex-wrap justify-between gap-3">
                            <Button variant="outline" asChild>
                                <a href={template.url(target.value)}>
                                    Unduh templat CSV
                                </a>
                            </Button>
                            <Button
                                onClick={runPreview}
                                disabled={http.data.file === null || http.processing}
                            >
                                Lanjut ke pratinjau
                            </Button>
                        </div>
                    </div>
                </Panel>
            )}

            {step === 1 && preview !== null && (
                <Panel title={`Pratinjau ${target.label}`}>
                    <p className="text-sm text-muted-foreground">
                        {preview.summary.total} baris terbaca:{' '}
                        {preview.summary.create} baru, {preview.summary.update}{' '}
                        diperbarui, {preview.summary.error} perlu diperbaiki.
                    </p>
                    {http.errors.file !== undefined && (
                        <Alert variant="destructive" className="mt-4">
                            <AlertDescription>{http.errors.file}</AlertDescription>
                        </Alert>
                    )}
                    {preview.summary.error > 0 && (
                        <label className="mt-4 flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={onlyErrors}
                                onCheckedChange={(checked) => setOnlyErrors(checked === true)}
                            />
                            Hanya tampilkan baris yang perlu diperbaiki
                        </label>
                    )}
                    <div className="mt-4">
                        <DataTable
                            head={[
                                'Baris',
                                'Nama',
                                keyLabel,
                                isStudents ? 'Kelas' : 'Tugas',
                                'Hasil cek',
                            ]}
                        >
                            {rows.map((row) => (
                                <TableRow key={row.line}>
                                    <TableCell className="text-muted-foreground">
                                        {row.line}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {row.name || '—'}
                                    </TableCell>
                                    <TableCell>{row.key || '—'}</TableCell>
                                    <TableCell>{row.detail || '—'}</TableCell>
                                    <TableCell className="whitespace-normal">
                                        {row.outcome === 'create' && (
                                            <Badge variant="secondary">Baru</Badge>
                                        )}
                                        {row.outcome === 'update' && (
                                            <Badge variant="outline">Diperbarui</Badge>
                                        )}
                                        {row.outcome === 'error' && (
                                            <ul className="flex flex-col gap-1 text-sm text-destructive">
                                                {row.messages.map((message) => (
                                                    <li key={message}>{message}</li>
                                                ))}
                                            </ul>
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </DataTable>
                    </div>
                    <div className="mt-6 flex flex-wrap justify-between gap-3">
                        <Button
                            variant="outline"
                            onClick={() => setStep(0)}
                            disabled={http.processing}
                        >
                            Kembali
                        </Button>
                        <Button
                            onClick={runImport}
                            disabled={ready === 0 || http.processing}
                        >
                            {ready === 0
                                ? 'Tidak ada baris yang siap'
                                : `Impor ${ready} baris yang siap`}
                        </Button>
                    </div>
                </Panel>
            )}

            {step === 2 && result !== null && (
                <Panel title="Selesai">
                    <p className="text-sm">
                        {result.created} baris ditambahkan, {result.updated}{' '}
                        diperbarui, {result.skipped} dilewati.
                    </p>
                    <div className="mt-6 flex flex-wrap gap-3">
                        <Button asChild>
                            <Link
                                href={isStudents ? studentsPage.url() : teachersPage.url()}
                            >
                                Lihat daftar {target.label}
                            </Link>
                        </Button>
                        <Button variant="outline" onClick={restart}>
                            Impor berkas lain
                        </Button>
                    </div>
                </Panel>
            )}
        </MasterPage>
    );
}
