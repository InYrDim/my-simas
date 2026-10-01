import {
    classShow,
    students as studentsIndex,
} from '@/actions/Modules/Core/App/Http/Controllers/MasterDataController';
import { Link } from '@inertiajs/react';

import { DataTable, DefinitionList, Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import { formatDate } from '../../../../Components/format';
import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import type { SchoolSummary, Student } from '../../../../types/master';

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
}: {
    school: SchoolSummary;
    student: Student;
    history: HistoryRow[];
}) {
    return (
        <MasterPage
            school={school}
            title={student.name}
            description={`NIS ${student.nis} · NISN ${student.nisn}`}
            back={{ href: studentsIndex.url(), label: 'Semua siswa' }}
            actions={<Button variant="outline">Ubah data</Button>}
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <div className="flex flex-col gap-6">
                    <Panel title="Biodata">
                        <DefinitionList
                            rows={[
                                ['Jenis kelamin', student.gender === 'L' ? 'Laki-laki' : 'Perempuan'],
                                ['Tanggal lahir', formatDate(student.birth)],
                                ['Status', <StatusBadge key="s" status={student.status} />],
                                [
                                    'Kelas',
                                    student.classId !== null ? (
                                        <Link
                                            key="c"
                                            href={classShow.url({ id: student.classId })}
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
                                ['Nama', student.guardian],
                                ['Telepon', student.guardianPhone],
                            ]}
                        />
                    </Panel>

                    <Panel title="Akun login">
                        <div className="flex flex-col gap-3">
                            <StatusBadge status={student.hasAccount ? 'linked' : 'unlinked'} />
                            {!student.hasAccount && (
                                <Button variant="outline">Buat akun</Button>
                            )}
                        </div>
                    </Panel>
                </div>

                <Panel title="Riwayat kelas" className="lg:col-span-2">
                    <DataTable head={['Tahun ajaran', 'Kelas', 'Keterangan']}>
                        {history.map((row) => (
                            <TableRow key={row.year}>
                                <TableCell>{row.year}</TableCell>
                                <TableCell>{row.class}</TableCell>
                                <TableCell>{row.note}</TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                </Panel>
            </div>
        </MasterPage>
    );
}
