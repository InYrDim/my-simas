import { Link } from '@inertiajs/react';
import { PlusIcon, PrinterIcon, UploadIcon } from 'lucide-react';
import { useRef, useState } from 'react';

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
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@shared/components/ui/dialog';
import { Skeleton } from '@shared/components/ui/skeleton';
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

interface StudentAction {
    key: string;
    label: string;
    allUrl: string;
    /** The student's id goes on the end. */
    studentUrl: string;
}

interface Preview {
    title: string;
    url: string;
}

/** The address of a module's print page, asking for its dialog layout. */
function embedded(url: string): string {
    return `${url}${url.includes('?') ? '&' : '?'}embed=1`;
}

/**
 * A module's print page inside a dialog: the page stays where it is, and
 * "Cetak" prints only what the frame shows.
 */
function PrintPreview({
    preview,
    onClose,
}: {
    preview: Preview | null;
    onClose: () => void;
}) {
    const frame = useRef<HTMLIFrameElement>(null);
    const [loaded, setLoaded] = useState(false);

    return (
        <Dialog
            open={preview !== null}
            onOpenChange={(open) => {
                if (!open) {
                    setLoaded(false);
                    onClose();
                }
            }}
        >
            <DialogContent className="sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>{preview?.title}</DialogTitle>
                    <DialogDescription>
                        Periksa hasilnya, lalu cetak atau simpan sebagai PDF.
                    </DialogDescription>
                </DialogHeader>
                <div className="relative h-[65vh] overflow-hidden rounded-md border bg-background">
                    {!loaded && (
                        <Skeleton className="absolute inset-0 rounded-none" />
                    )}
                    {preview !== null && (
                        <iframe
                            ref={frame}
                            title={preview.title}
                            src={embedded(preview.url)}
                            className="size-full"
                            onLoad={() => setLoaded(true)}
                        />
                    )}
                </div>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Tutup</Button>
                    </DialogClose>
                    <Button
                        disabled={!loaded}
                        onClick={() => frame.current?.contentWindow?.print()}
                    >
                        <PrinterIcon />
                        Cetak
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

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
    studentActions,
}: {
    school: SchoolSummary;
    students: Student[];
    pagination: Pagination;
    filters: { q: string; class: string; status: string };
    classes: ClassOption[];
    /** Actions other modules add, such as printing the static QR. */
    studentActions: StudentAction[];
}) {
    const url = index.url();
    const [preview, setPreview] = useState<Preview | null>(null);
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
                <>
                    {studentActions.length > 0 && (
                        <div className="mb-3 flex flex-wrap justify-end gap-2">
                            {studentActions.map((action) => (
                                <Button
                                    key={action.key}
                                    variant="outline"
                                    onClick={() =>
                                        setPreview({
                                            title: `${action.label} — semua siswa`,
                                            url: action.allUrl,
                                        })
                                    }
                                >
                                    <PrinterIcon />
                                    Cetak semua {action.label}
                                </Button>
                            ))}
                        </div>
                    )}
                    <DataTable
                        head={[
                            'Nama',
                            'NIS / NISN',
                            'Kelas',
                            'Status',
                            ...(studentActions.length > 0 ? ['Aksi'] : []),
                        ]}
                    >
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
                                    {student.nisn !== null &&
                                        ` / ${student.nisn}`}
                                </TableCell>
                                <TableCell>{student.class ?? '—'}</TableCell>
                                <TableCell>
                                    <StatusBadge status={student.status} />
                                </TableCell>
                                {studentActions.length > 0 && (
                                    <TableCell>
                                        {student.status === 'active' &&
                                            studentActions.map((action) => (
                                                <Button
                                                    key={action.key}
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setPreview({
                                                            title: `${action.label} — ${student.name}`,
                                                            url: `${action.studentUrl}${student.id}`,
                                                        })
                                                    }
                                                >
                                                    <PrinterIcon />
                                                    Cetak {action.label}
                                                </Button>
                                            ))}
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </DataTable>
                </>
            )}

            <ListPager url={url} filters={initial} pagination={pagination} />

            <PrintPreview preview={preview} onClose={() => setPreview(null)} />
        </MasterPage>
    );
}
