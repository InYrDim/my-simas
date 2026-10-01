import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

import {
    destroy,
    index as extracurricularsIndex,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/ExtracurricularController';
import {
    destroy as removeMember,
    store as addMember,
} from '@/actions/Modules/Core/App/Http/Controllers/ExtracurricularMemberController';
import { show as studentShow } from '@/actions/Modules/Core/App/Http/Controllers/StudentController';
import { DataTable, DefinitionList, EmptyState, Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../../../Components/ConfirmAction';
import ExtracurricularForm from '../../../../Components/ExtracurricularForm';
import FormDialog from '../../../../Components/FormDialog';
import { InputField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type {
    Extracurricular,
    SchoolSummary,
    Student,
    Teacher,
} from '../../../../types/master';

/** One extracurricular: summary and member list. */
export default function ExtracurricularsShow({
    school,
    extracurricular,
    teachers,
}: {
    school: SchoolSummary;
    extracurricular: Extracurricular & { memberList: Student[] };
    teachers: Teacher[];
}) {
    const members = extracurricular.memberList;

    return (
        <MasterPage
            school={school}
            title={extracurricular.name}
            description={extracurricular.schedule ?? undefined}
            back={{ href: extracurricularsIndex.url(), label: 'Semua ekstrakurikuler' }}
            mock={false}
            actions={
                <>
                    <ConfirmAction
                        route={destroy(extracurricular.id)}
                        title={`Hapus ${extracurricular.name}?`}
                        description="Kegiatan beserta daftar anggotanya akan dihapus."
                        confirmLabel="Hapus"
                        trigger={<Button variant="outline">Hapus</Button>}
                    />
                    <FormDialog
                        route={update(extracurricular.id)}
                        title={`Ubah ${extracurricular.name}`}
                        trigger={<Button variant="outline">Ubah</Button>}
                    >
                        <ExtracurricularForm item={extracurricular} teachers={teachers} />
                    </FormDialog>
                    <FormDialog
                        route={addMember(extracurricular.id)}
                        title="Tambah anggota"
                        description="Masukkan NIS siswa aktif yang akan bergabung."
                        trigger={
                            <Button>
                                <PlusIcon />
                                Tambah anggota
                            </Button>
                        }
                    >
                        <InputField label="NIS" id="nis" />
                    </FormDialog>
                </>
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <Panel title="Ringkasan">
                    <DefinitionList
                        rows={[
                            ['Pembina', extracurricular.coach ?? '—'],
                            ['Jadwal', extracurricular.schedule ?? '—'],
                            ['Jenis', extracurricular.kind],
                            ['Anggota', extracurricular.members],
                        ]}
                    />
                </Panel>

                <Panel title="Anggota" className="lg:col-span-2">
                    {members.length === 0 ? (
                        <EmptyState>Belum ada anggota.</EmptyState>
                    ) : (
                        <DataTable head={['Nama', 'NIS', 'Kelas', '']}>
                            {members.map((student) => (
                                <TableRow key={student.id}>
                                    <TableCell className="font-medium">
                                        <Link
                                            href={studentShow.url(student.id)}
                                            className="hover:underline"
                                        >
                                            {student.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>{student.nis}</TableCell>
                                    <TableCell>{student.class ?? '—'}</TableCell>
                                    <TableCell className="text-right">
                                        <ConfirmAction
                                            route={removeMember({
                                                extracurricular: extracurricular.id,
                                                student: student.id,
                                            })}
                                            title={`Keluarkan ${student.name}?`}
                                            description="Siswa dikeluarkan dari kegiatan ini."
                                            confirmLabel="Keluarkan"
                                            trigger={
                                                <Button variant="ghost" size="sm">
                                                    Keluarkan
                                                </Button>
                                            }
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </DataTable>
                    )}
                </Panel>
            </div>
        </MasterPage>
    );
}
