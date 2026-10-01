import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

import {
    extracurriculars as extracurricularsIndex,
    studentShow,
} from '@/actions/Modules/Core/App/Http/Controllers/MasterDataController';
import { DataTable, DefinitionList, EmptyState, Panel } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import MasterPage from '../../../../Components/MasterPage';
import type { Extracurricular, SchoolSummary } from '../../../../types/master';

/** One extracurricular: summary and member list. */
export default function ExtracurricularsShow({
    school,
    extracurricular,
}: {
    school: SchoolSummary;
    extracurricular: Extracurricular;
}) {
    const members = extracurricular.memberList ?? [];

    return (
        <MasterPage
            school={school}
            title={extracurricular.name}
            description={extracurricular.schedule}
            back={{ href: extracurricularsIndex.url(), label: 'Semua ekstrakurikuler' }}
            actions={
                <Button>
                    <PlusIcon />
                    Tambah anggota
                </Button>
            }
        >
            <div className="grid gap-6 lg:grid-cols-3">
                <Panel title="Ringkasan">
                    <DefinitionList
                        rows={[
                            ['Pembina', extracurricular.coach],
                            ['Jadwal', extracurricular.schedule],
                            ['Jenis', extracurricular.kind],
                            ['Anggota', extracurricular.members],
                        ]}
                    />
                </Panel>

                <Panel title="Anggota" className="lg:col-span-2">
                    {members.length === 0 ? (
                        <EmptyState>Belum ada anggota.</EmptyState>
                    ) : (
                        <DataTable head={['Nama', 'NIS', 'Kelas']}>
                            {members.map((student) => (
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
                                    <TableCell>{student.class ?? '—'}</TableCell>
                                </TableRow>
                            ))}
                        </DataTable>
                    )}
                </Panel>
            </div>
        </MasterPage>
    );
}
