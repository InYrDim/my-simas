import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

import {
    index,
    show,
    store,
} from '@/actions/Modules/Core/App/Http/Controllers/TeacherController';
import { DataTable, EmptyState, OptionSelect } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';
import ListPager, { useListFilters } from '@shared/components/ListPager';

import FormDialog from '../../../../Components/FormDialog';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import TeacherForm from '../../../../Components/TeacherForm';
import type { Pagination, SchoolSummary, Teacher } from '../../../../types/master';

/** Guru & Tenaga Kependidikan, with search and employment filter. */
export default function TeachersIndex({
    school,
    teachers,
    pagination,
    filters: initial,
}: {
    school: SchoolSummary;
    teachers: Teacher[];
    pagination: Pagination;
    filters: { q: string; employment: string };
}) {
    const url = index.url();
    const { filters, set } = useListFilters(url, initial);

    return (
        <MasterPage
            school={school}
            title="Guru & Tendik"
            description="Data guru dan tenaga kependidikan. Akun login bersifat opsional."
            mock={false}
            actions={
                <FormDialog
                    route={store()}
                    title="Tambah guru / tendik"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah guru / tendik
                        </Button>
                    }
                >
                    <TeacherForm />
                </FormDialog>
            }
        >
            <div className="mb-4 grid gap-3 sm:grid-cols-[1fr_14rem]">
                <Input
                    aria-label="Cari nama"
                    placeholder="Cari nama, NIP, atau NUPTK…"
                    value={filters.q}
                    onChange={(event) => set('q', event.target.value)}
                />
                <OptionSelect
                    label="Status kepegawaian"
                    allLabel="Semua status"
                    value={filters.employment}
                    onChange={(value) => set('employment', value)}
                    options={['PNS', 'GTY', 'GTT', 'Honorer'].map((value) => ({
                        value,
                        label: value,
                    }))}
                />
            </div>

            {teachers.length === 0 ? (
                <EmptyState>
                    {initial.q !== '' || initial.employment !== ''
                        ? 'Tidak ada guru yang cocok.'
                        : 'Belum ada guru atau tenaga kependidikan.'}
                </EmptyState>
            ) : (
                <DataTable
                    head={['Nama', 'NIP / NUPTK', 'Status', 'Tugas', 'Akun login']}
                >
                    {teachers.map((teacher) => (
                        <TableRow key={teacher.id}>
                            <TableCell className="font-medium">
                                <Link
                                    href={show.url(teacher.id)}
                                    className="hover:underline"
                                >
                                    {teacher.name}
                                </Link>
                            </TableCell>
                            <TableCell className="font-mono text-xs">
                                {teacher.nip ?? teacher.nuptk ?? '—'}
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

            <ListPager url={url} filters={initial} pagination={pagination} />
        </MasterPage>
    );
}
