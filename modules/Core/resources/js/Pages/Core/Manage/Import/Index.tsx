import { useState } from 'react';

import { DataTable, OptionSelect, Panel } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';
import { cn } from '@shared/lib/utils';

import { InputField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type { SchoolSummary, Student } from '../../../../types/master';

const steps = ['Unggah berkas', 'Pratinjau', 'Selesai'];

const targets = [
    { value: 'students', label: 'Siswa' },
    { value: 'teachers', label: 'Guru & Tendik' },
];

/**
 * Impor Data: bring base records in from CSV in three steps — upload,
 * preview with validation, confirm.
 */
export default function ImportIndex({
    school,
    preview,
}: {
    school: SchoolSummary;
    preview: Student[];
}) {
    const [step, setStep] = useState(0);
    const [target, setTarget] = useState('students');

    return (
        <MasterPage
            school={school}
            title="Impor Data"
            description="Masukkan data dasar secara massal dari berkas CSV."
            width="max-w-4xl"
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

            {step === 0 && (
                <Panel title="Unggah berkas">
                    <div className="flex flex-col gap-6">
                        <div className="max-w-xs">
                            <OptionSelect
                                label="Jenis data"
                                value={target}
                                onChange={setTarget}
                                options={targets}
                            />
                        </div>
                        <InputField
                            label="Berkas CSV"
                            id="file"
                            type="file"
                            accept=".csv"
                            hint="Kolom wajib: nama, NIS, NISN, jenis kelamin, kelas."
                        />
                        <div className="flex justify-between gap-3">
                            <Button variant="outline">Unduh templat CSV</Button>
                            <Button onClick={() => setStep(1)}>Lanjut ke pratinjau</Button>
                        </div>
                    </div>
                </Panel>
            )}

            {step === 1 && (
                <Panel title="Pratinjau">
                    <p className="mb-4 text-sm text-muted-foreground">
                        {preview.length} baris terbaca, 1 baris perlu diperbaiki.
                    </p>
                    <DataTable head={['Nama', 'NIS', 'NISN', 'Kelas', 'Hasil cek']}>
                        {preview.map((row, index) => (
                            <TableRow key={row.id}>
                                <TableCell className="font-medium">{row.name}</TableCell>
                                <TableCell>{row.nis}</TableCell>
                                <TableCell>{row.nisn}</TableCell>
                                <TableCell>{row.class ?? '—'}</TableCell>
                                <TableCell>
                                    {index === 3 ? (
                                        <Badge variant="destructive">NIS sudah dipakai</Badge>
                                    ) : (
                                        <Badge variant="secondary">Siap</Badge>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                    <div className="mt-6 flex justify-between gap-3">
                        <Button variant="outline" onClick={() => setStep(0)}>
                            Kembali
                        </Button>
                        <Button onClick={() => setStep(2)}>
                            Impor {preview.length - 1} baris yang siap
                        </Button>
                    </div>
                </Panel>
            )}

            {step === 2 && (
                <Panel title="Selesai">
                    <p className="text-sm">
                        {preview.length - 1} baris berhasil diimpor, 1 baris dilewati.
                    </p>
                    <div className="mt-6">
                        <Button variant="outline" onClick={() => setStep(0)}>
                            Impor berkas lain
                        </Button>
                    </div>
                </Panel>
            )}
        </MasterPage>
    );
}
