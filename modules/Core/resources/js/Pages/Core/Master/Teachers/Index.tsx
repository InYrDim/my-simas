import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';
import { useState } from 'react';

import { teacherShow } from '@/actions/Modules/Core/App/Http/Controllers/MasterDataController';
import { DataTable, EmptyState, OptionSelect } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';

import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import type { SchoolSummary, Teacher } from '../../../../types/master';

/** Guru & Tenaga Kependidikan, with search and employment filter. */
export default function TeachersIndex({
    school,
    teachers,
}: {
    school: SchoolSummary;
    teachers: Teacher[];
}) {
    const [query, setQuery] = useState('');
    const [employment, setEmployment] = useState('');

    const rows = teachers.filter(
        (teacher) =>
            teacher.name.toLowerCase().includes(query.toLowerCase()) &&
            (employment === '' || teacher.employment === employment),
    );

    return (
        <MasterPage
            school={school}
            title="Guru & Tendik"
            description="Data guru dan tenaga kependidikan. Akun login bersifat opsional."
            actions={
                <FormDialog
                    title="Tambah guru / tendik"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah guru / tendik
                        </Button>
                    }
                >
                    <InputField label="Nama lengkap" id="name" />
                    <InputField label="NIP" id="nip" />
                    <InputField label="NUPTK" id="nuptk" />
                    <SelectField
                        label="Status kepegawaian"
                        id="employment"
                        options={['PNS', 'GTY', 'GTT', 'Honorer']}
                    />
                </FormDialog>
            }
        >
            <div className="mb-4 grid gap-3 sm:grid-cols-[1fr_14rem]">
                <Input
                    aria-label="Cari nama"
                    placeholder="Cari nama…"
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                />
                <OptionSelect
                    label="Status kepegawaian"
                    allLabel="Semua status"
                    value={employment}
                    onChange={setEmployment}
                    options={['PNS', 'GTY', 'GTT', 'Honorer'].map((value) => ({
                        value,
                        label: value,
                    }))}
                />
            </div>

            {rows.length === 0 ? (
                <EmptyState>Tidak ada guru yang cocok.</EmptyState>
            ) : (
                <DataTable
                    head={['Nama', 'NIP / NUPTK', 'Status', 'Tugas', 'Akun login']}
                >
                    {rows.map((teacher) => (
                        <TableRow key={teacher.id}>
                            <TableCell className="font-medium">
                                <Link
                                    href={teacherShow.url({ id: teacher.id })}
                                    className="hover:underline"
                                >
                                    {teacher.name}
                                </Link>
                            </TableCell>
                            <TableCell className="font-mono text-xs">
                                {teacher.nip !== '—' ? teacher.nip : teacher.nuptk}
                            </TableCell>
                            <TableCell>{teacher.employment}</TableCell>
                            <TableCell>{teacher.duty}</TableCell>
                            <TableCell>
                                <StatusBadge
                                    status={teacher.hasAccount ? 'linked' : 'unlinked'}
                                />
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </MasterPage>
    );
}
