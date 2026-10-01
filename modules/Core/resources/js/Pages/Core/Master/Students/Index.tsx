import { Link } from '@inertiajs/react';
import { PlusIcon, UploadIcon } from 'lucide-react';
import { useState } from 'react';

import {
    importData,
    studentShow,
} from '@/actions/Modules/Core/App/Http/Controllers/MasterDataController';
import { DataTable, EmptyState, OptionSelect } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import type { ClassGroup, SchoolSummary, Student } from '../../../../types/master';

const statusOptions = [
    { value: 'active', label: 'Aktif' },
    { value: 'graduated', label: 'Lulus' },
    { value: 'transferred', label: 'Pindah' },
    { value: 'left', label: 'Keluar' },
];

/** Siswa: search, filter by class and status, create and CSV import. */
export default function StudentsIndex({
    school,
    students,
    classes,
}: {
    school: SchoolSummary;
    students: Student[];
    classes: ClassGroup[];
}) {
    const [query, setQuery] = useState('');
    const [classId, setClassId] = useState('');
    const [status, setStatus] = useState('');

    const rows = students.filter(
        (student) =>
            `${student.name} ${student.nis} ${student.nisn}`
                .toLowerCase()
                .includes(query.toLowerCase()) &&
            (classId === '' || String(student.classId) === classId) &&
            (status === '' || student.status === status),
    );

    return (
        <MasterPage
            school={school}
            title="Siswa"
            description="Data siswa dan status keanggotaannya. Siswa tidak wajib punya akun login."
            actions={
                <>
                    <Button asChild variant="outline">
                        <Link href={importData.url()}>
                            <UploadIcon />
                            Impor CSV
                        </Link>
                    </Button>

                    <FormDialog
                        title="Tambah siswa"
                        trigger={
                            <Button>
                                <PlusIcon />
                                Tambah siswa
                            </Button>
                        }
                    >
                        <InputField label="Nama lengkap" id="name" />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <InputField label="NIS" id="nis" />
                            <InputField label="NISN" id="nisn" />
                        </div>
                        <SelectField
                            label="Jenis kelamin"
                            id="gender"
                            options={[
                                { value: 'L', label: 'Laki-laki' },
                                { value: 'P', label: 'Perempuan' },
                            ]}
                        />
                    </FormDialog>
                </>
            }
        >
            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <Input
                    aria-label="Cari siswa"
                    placeholder="Cari nama, NIS, atau NISN…"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                />
                <OptionSelect
                    label="Kelas"
                    allLabel="Semua kelas"
                    value={classId}
                    onChange={setClassId}
                    options={classes.map((item) => ({
                        value: String(item.id),
                        label: item.name,
                    }))}
                />
                <OptionSelect
                    label="Status"
                    allLabel="Semua status"
                    value={status}
                    onChange={setStatus}
                    options={statusOptions}
                />
            </div>

            {rows.length === 0 ? (
                <EmptyState>Tidak ada siswa yang cocok.</EmptyState>
            ) : (
                <DataTable head={['Nama', 'NIS / NISN', 'Kelas', 'Status']}>
                    {rows.map((student) => (
                        <TableRow key={student.id}>
                            <TableCell className="font-medium">
                                <Link
                                    href={studentShow.url({ id: student.id })}
                                    className="hover:underline"
                                >
                                    {student.name}
                                </Link>
                            </TableCell>
                            <TableCell className="font-mono text-xs">
                                {student.nis} / {student.nisn}
                            </TableCell>
                            <TableCell>{student.class ?? '—'}</TableCell>
                            <TableCell>
                                <StatusBadge status={student.status} />
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </MasterPage>
    );
}
