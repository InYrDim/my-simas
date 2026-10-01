import { Link } from '@inertiajs/react';

import { show as classShow } from '@/actions/Modules/Core/App/Http/Controllers/ClassGroupController';
import {
    destroy,
    index as studentsIndex,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/StudentController';
import {
    DataTable,
    DefinitionList,
    EmptyState,
    Panel,
} from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../../../Components/ConfirmAction';
import FormDialog from '../../../../Components/FormDialog';
import { formatDate } from '../../../../Components/format';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import StudentForm from '../../../../Components/StudentForm';
import type { ClassOption, SchoolSummary, Student } from '../../../../types/master';

interface HistoryRow {
    year: string;
    class: string;
    note: string;
}

/** One student: biodata, guardian, class history and optional account. */
export default function StudentsShow({
    school,
    student,
    history,
    classes,
}: {
    school: SchoolSummary;
    student: Student;
    history: HistoryRow[];
    classes: ClassOption[];
}) {
    return (
        <MasterPage
            school={school}
            title={student.name}
            description={`NIS ${student.nis}${student.nisn !== null ? ` · NISN ${student.nisn}` : ''}`}
            back={{ href: studentsIndex.url(), label: 'Semua siswa' }}
            mock={false}
            actions={
                <>
                    <ConfirmAction
                        route={destroy(student.id)}
                        title={`Hapus ${student.name}?`}
                        description="Data siswa beserta riwayat kelas dan keanggotaan ekstrakurikulernya akan dihapus."
                        confirmLabel="Hapus"
                        trigger={<Button variant="outline">Hapus</Button>}
                    />
                    <FormDialog
                        route={update(student.id)}
                        title={`Ubah ${student.name}`}
                        trigger={<Button variant="outline">Ubah data</Button>}
                    >
                        <StudentForm student={student} classes={classes} />
                    </FormDialog>
                </>
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="flex flex-col gap-6">
                    <Panel title="Biodata">
                        <DefinitionList
                            rows={[
                                ['Jenis kelamin', student.gender === 'L' ? 'Laki-laki' : 'Perempuan'],
                                [
                                    'Tanggal lahir',
                                    student.birth !== null ? formatDate(student.birth) : '—',
                                ],
                                ['Status', <StatusBadge key="s" status={student.status} />],
                                [
                                    'Kelas',
                                    student.classId !== null ? (
                                        <Link
                                            key="c"
                                            href={classShow.url(student.classId)}
                                            className="hover:underline"
                                        >
                                            {student.class}
                                        </Link>
                                    ) : (
                                        '—'
                                    ),
                                ],
                            ]}
                        />
                    </Panel>

                    <Panel title="Wali / orang tua">
                        <DefinitionList
                            rows={[
                                ['Nama', student.guardian ?? '—'],
                                ['Telepon', student.guardianPhone ?? '—'],
                            ]}
                        />
                    </Panel>

                    <Panel title="Akun login">
                        <StatusBadge status={student.hasAccount ? 'linked' : 'unlinked'} />
                    </Panel>
                </div>

                <Panel title="Riwayat kelas" className="lg:col-span-2">
                    {history.length === 0 ? (
                        <EmptyState>Belum ada riwayat kelas.</EmptyState>
                    ) : (
                        <DataTable head={['Tahun ajaran', 'Kelas', 'Keterangan']}>
                            {history.map((row) => (
                                <TableRow key={row.year}>
                                    <TableCell>{row.year}</TableCell>
                                    <TableCell>{row.class}</TableCell>
                                    <TableCell>{row.note}</TableCell>
                                </TableRow>
                            ))}
                        </DataTable>
                    )}
                </Panel>
            </div>
        </MasterPage>
    );
}
