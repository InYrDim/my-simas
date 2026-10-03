import { Link } from '@inertiajs/react';
import { PlusIcon, UploadIcon } from 'lucide-react';

import { index as importPage } from '@/actions/Modules/Core/App/Http/Controllers/ImportController';
import {
    index,
    show,
    store,
} from '@/actions/Modules/Core/App/Http/Controllers/StudentController';
import {
    DataTable,
    EmptyState,
    OptionSelect,
} from '@shared/components/page-parts';
import Can from '@shared/components/Can';
import { Button } from '@shared/components/ui/button';
import { Input } from '@shared/components/ui/input';
import { TableCell, TableRow } from '@shared/components/ui/table';
import ListPager, { useListFilters } from '@shared/components/ListPager';

import FormDialog from '../../../../Components/FormDialog';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import StudentForm from '../../../../Components/StudentForm';
import type {
    ClassOption,
    Pagination,
    SchoolSummary,
    Student,
} from '../../../../types/master';

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
    pagination,
    filters: initial,
    classes,
}: {
    school: SchoolSummary;
    students: Student[];
    pagination: Pagination;
    filters: { q: string; class: string; status: string };
    classes: ClassOption[];
}) {
    const url = index.url();
    const { filters, set } = useListFilters(url, initial);
    const filtered =
        initial.q !== '' || initial.class !== '' || initial.status !== '';

    return (
        <MasterPage
            school={school}
            title="Siswa"
            description="Data siswa dan status keanggotaannya. Siswa tidak wajib punya akun login."
            mock={false}
            actions={
                <>
                    <Can permission="core.master.manage">
                        <Button asChild variant="outline">
                            <Link href={importPage.url()}>
                                <UploadIcon />
                                Impor CSV
                            </Link>
                        </Button>
                    </Can>

                    <FormDialog
                        route={store()}
                        title="Tambah siswa"
                        trigger={
                            <Button>
                                <PlusIcon />
                                Tambah siswa
                            </Button>
                        }
                    >
                        <StudentForm classes={classes} />
                    </FormDialog>
                </>
            }
        >
            <div className="mb-4 grid gap-3 sm:grid-cols-3">
                <Input
                    aria-label="Cari siswa"
                    placeholder="Cari nama, NIS, atau NISN…"
                    value={filters.q}
                    onChange={(event) => set('q', event.target.value)}
                />
                <OptionSelect
                    label="Kelas"
                    allLabel="Semua kelas"
                    value={filters.class}
                    onChange={(value) => set('class', value)}
                    options={classes.map((item) => ({
                        value: String(item.id),
                        label: item.name,
                    }))}
                />
                <OptionSelect
                    label="Status"
                    allLabel="Semua status"
                    value={filters.status}
                    onChange={(value) => set('status', value)}
                    options={statusOptions}
                />
            </div>

            {students.length === 0 ? (
                <EmptyState>
                    {filtered
                        ? 'Tidak ada siswa yang cocok.'
                        : 'Belum ada siswa.'}
                </EmptyState>
            ) : (
                <DataTable head={['Nama', 'NIS / NISN', 'Kelas', 'Status']}>
                    {students.map((student) => (
                        <TableRow key={student.id}>
                            <TableCell className="font-medium">
                                <Link
                                    href={show.url(student.id)}
                                    className="hover:underline"
                                >
                                    {student.name}
                                </Link>
                            </TableCell>
                            <TableCell className="font-mono text-xs">
                                {student.nis}
                                {student.nisn !== null && ` / ${student.nisn}`}
                            </TableCell>
                            <TableCell>{student.class ?? '—'}</TableCell>
                            <TableCell>
                                <StatusBadge status={student.status} />
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}

            <ListPager url={url} filters={initial} pagination={pagination} />
        </MasterPage>
    );
}
