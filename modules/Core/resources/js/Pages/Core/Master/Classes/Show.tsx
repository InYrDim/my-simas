import { Link } from '@inertiajs/react';

import { storeForClass } from '@/actions/Modules/Core/App/Http/Controllers/AccountController';
import { index as placement } from '@/actions/Modules/Core/App/Http/Controllers/PlacementController';
import { show as studentShow } from '@/actions/Modules/Core/App/Http/Controllers/StudentController';
import { index as assignmentsPage } from '@/actions/Modules/Core/App/Http/Controllers/TeachingAssignmentController';
import { index as classesIndex } from '@/actions/Modules/Core/App/Http/Controllers/ClassGroupController';
import {
    DataTable,
    DefinitionList,
    EmptyState,
    Panel,
} from '@shared/components/page-parts';
import Can from '@shared/components/Can';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../../../Components/ConfirmAction';
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
    canCreateAccounts,
    assignments,
}: {
    school: SchoolSummary;
    class: ClassGroup;
    students: Student[];
    canCreateAccounts: boolean;
    assignments: Assignment[];
}) {
    const withoutAccount = students.filter(
        (student) => !student.hasAccount,
    ).length;

    return (
        <MasterPage
            school={school}
            title={`Kelas ${group.name}`}
            description={`Tahun ajaran ${group.year}`}
            back={{ href: classesIndex.url(), label: 'Semua kelas' }}
            mock={false}
            actions={
                <Can permission="core.academic.manage">
                    <Button asChild variant="outline">
                        <Link
                            href={assignmentsPage.url({
                                query: { kelas: group.id },
                            })}
                        >
                            Atur pengampu
                        </Link>
                    </Button>
                    <Button asChild variant="outline">
                        <Link
                            href={placement.url({ query: { kelas: group.id } })}
                        >
                            Pindahkan siswa
                        </Link>
                    </Button>
                </Can>
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <Panel title="Ringkasan" className="lg:col-span-1">
                    <DefinitionList
                        rows={[
                            [school.homeroomLabel, group.homeroom ?? '—'],
                            ['Ruangan', group.room ?? '—'],
                            ['Tingkat', `Kelas ${group.grade}`],
                            ['Jurusan', group.major ?? '—'],
                            ['Jumlah siswa', group.students],
                        ]}
                    />
                </Panel>

                <div className="flex flex-col gap-6 lg:col-span-2">
                    <Panel title="Pengampu mata pelajaran">
                        {assignments.length === 0 ? (
                            <EmptyState>
                                Belum ada pengampu untuk kelas ini.
                            </EmptyState>
                        ) : (
                            <DataTable
                                head={['Mata pelajaran', 'Guru', 'JP/minggu']}
                            >
                                {assignments.map((row) => (
                                    <TableRow
                                        key={`${row.subject}-${row.teacher}`}
                                    >
                                        <TableCell>{row.subject}</TableCell>
                                        <TableCell>{row.teacher}</TableCell>
                                        <TableCell>{row.hours}</TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        )}
                    </Panel>

                    <Panel
                        title="Daftar siswa"
                        actions={
                            canCreateAccounts &&
                            withoutAccount > 0 && (
                                <ConfirmAction
                                    route={storeForClass(group.id)}
                                    title={`Buatkan akun untuk siswa ${group.name}?`}
                                    description={`${withoutAccount} siswa belum punya akun. Nama pengguna = NIS, kata sandi awal = tanggal lahir (ddmmyyyy). Siswa tanpa tanggal lahir dilewati.`}
                                    confirmLabel="Buatkan akun"
                                    trigger={
                                        <Button variant="outline" size="sm">
                                            Buatkan akun siswa
                                        </Button>
                                    }
                                />
                            )
                        }
                    >
                        {students.length === 0 ? (
                            <EmptyState>
                                Belum ada siswa di kelas ini.
                            </EmptyState>
                        ) : (
                            <DataTable head={['Nama', 'NIS', 'Status', 'Akun']}>
                                {students.map((student) => (
                                    <TableRow key={student.id}>
                                        <TableCell className="font-medium">
                                            <Link
                                                href={studentShow.url(
                                                    student.id,
                                                )}
                                                className="hover:underline"
                                            >
                                                {student.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>{student.nis}</TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={student.status}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                status={
                                                    student.hasAccount
                                                        ? 'linked'
                                                        : 'unlinked'
                                                }
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        )}
                    </Panel>
                </div>
            </div>
        </MasterPage>
    );
}
