import { Link } from '@inertiajs/react';

import {
    assignments as assignmentsPage,
    classes as classesIndex,
    placement,
    studentShow,
} from '@/actions/Modules/Core/App/Http/Controllers/MasterDataController';
import { DataTable, DefinitionList, Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import MasterPage from '../../../../Components/MasterPage';
import StatusBadge from '../../../../Components/StatusBadge';
import type {
    Assignment,
    ClassGroup,
    SchoolSummary,
    Student,
} from '../../../../types/master';

/** One rombel: homeroom, room, students and subject teachers. */
export default function ClassesShow({
    school,
    class: group,
    students,
    assignments,
}: {
    school: SchoolSummary;
    class: ClassGroup;
    students: Student[];
    assignments: Assignment[];
}) {
    return (
        <MasterPage
            school={school}
            title={`Kelas ${group.name}`}
            description="Tahun ajaran 2025/2026"
            back={{ href: classesIndex.url(), label: 'Semua kelas' }}
            actions={
                <>
                    <Button asChild variant="outline">
                        <Link href={assignmentsPage.url()}>Atur pengampu</Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={placement.url()}>Pindahkan siswa</Link>
                    </Button>
                </>
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <Panel title="Ringkasan" className="lg:col-span-1">
                    <DefinitionList
                        rows={[
                            [school.homeroomLabel, group.homeroom],
                            ['Ruangan', group.room],
                            ['Tingkat', `Kelas ${group.grade}`],
                            ['Jurusan', group.major ?? '—'],
                            ['Jumlah siswa', group.students],
                        ]}
                    />
                </Panel>

                <div className="flex flex-col gap-6 lg:col-span-2">
                    <Panel title="Pengampu mata pelajaran">
                        <DataTable head={['Mata pelajaran', 'Guru', 'JP/minggu']}>
                            {assignments.map((row) => (
                                <TableRow key={`${row.subject}-${row.teacher}`}>
                                    <TableCell>{row.subject}</TableCell>
                                    <TableCell>{row.teacher}</TableCell>
                                    <TableCell>{row.hours}</TableCell>
                                </TableRow>
                            ))}
                        </DataTable>
                    </Panel>

                    <Panel title="Daftar siswa">
                        <DataTable head={['Nama', 'NIS', 'Status']}>
                            {students.map((student) => (
                                <TableRow key={student.id}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={studentShow.url({ id: student.id })}
                                            className="hover:underline"
                                        >
                                            {student.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{student.nis}</TableCell>
                                    <TableCell>
                                        <StatusBadge status={student.status} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </DataTable>
                    </Panel>
                </div>
            </div>
        </MasterPage>
    );
}
