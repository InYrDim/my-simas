import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

import {
    show,
    store,
} from '@/actions/Modules/Core/App/Http/Controllers/ExtracurricularController';
import { DataTable, EmptyState } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ExtracurricularForm from '../../../../Components/ExtracurricularForm';
import FormDialog from '../../../../Components/FormDialog';
import MasterPage from '../../../../Components/MasterPage';
import type { Extracurricular, SchoolSummary, Teacher } from '../../../../types/master';

/** Ekstrakurikuler: activities with coach, schedule and member count. */
export default function ExtracurricularsIndex({
    school,
    extracurriculars,
    teachers,
}: {
    school: SchoolSummary;
    extracurriculars: Extracurricular[];
    teachers: Teacher[];
}) {
    return (
        <MasterPage
            school={school}
            title="Ekstrakurikuler"
            description="Kegiatan di luar jam pelajaran beserta pembina dan anggotanya."
            width="max-w-5xl"
            mock={false}
            actions={
                <FormDialog
                    route={store()}
                    title="Tambah ekstrakurikuler"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah ekstrakurikuler
                        </Button>
                    }
                >
                    <ExtracurricularForm teachers={teachers} />
                </FormDialog>
            }
        >
            {extracurriculars.length === 0 ? (
                <EmptyState>Belum ada ekstrakurikuler.</EmptyState>
            ) : (
                <DataTable head={['Kegiatan', 'Pembina', 'Jadwal', 'Jenis', 'Anggota']}>
                    {extracurriculars.map((item) => (
                        <TableRow key={item.id}>
                            <TableCell className="font-medium">
                                <Link href={show.url(item.id)} className="hover:underline">
                                    {item.name}
                                </Link>
                            </TableCell>
                            <TableCell>{item.coach ?? '—'}</TableCell>
                            <TableCell>{item.schedule ?? '—'}</TableCell>
                            <TableCell>
                                <Badge variant={item.kind === 'Wajib' ? 'default' : 'secondary'}>
                                    {item.kind}
                                </Badge>
                            </TableCell>
                            <TableCell>{item.members}</TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </MasterPage>
    );
}
