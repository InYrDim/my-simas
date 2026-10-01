import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

import { extracurricularShow } from '@/actions/Modules/Core/App/Http/Controllers/MasterDataController';
import { DataTable } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
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
            actions={
                <FormDialog
                    title="Tambah ekstrakurikuler"
                    trigger={
                        <Button>
                            <PlusIcon />
                            Tambah ekstrakurikuler
                        </Button>
                    }
                >
                    <InputField label="Nama kegiatan" id="name" />
                    <SelectField
                        label="Pembina"
                        id="coach"
                        options={teachers.map((teacher) => teacher.name)}
                    />
                    <InputField label="Jadwal" id="schedule" placeholder="Jumat 14.30–16.00" />
                    <SelectField label="Jenis" id="kind" options={['Wajib', 'Pilihan']} />
                </FormDialog>
            }
        >
            <DataTable head={['Kegiatan', 'Pembina', 'Jadwal', 'Jenis', 'Anggota']}>
                {extracurriculars.map((item) => (
                    <TableRow key={item.id}>
                        <TableCell className="font-medium">
                            <Link
                                href={extracurricularShow.url({ id: item.id })}
                                className="hover:underline"
                            >
                                {item.name}
                            </Link>
                        </TableCell>
                        <TableCell>{item.coach}</TableCell>
                        <TableCell>{item.schedule}</TableCell>
                        <TableCell>
                            <Badge variant={item.kind === 'Wajib' ? 'default' : 'secondary'}>
                                {item.kind}
                            </Badge>
                        </TableCell>
                        <TableCell>{item.members}</TableCell>
                    </TableRow>
                ))}
            </DataTable>
        </MasterPage>
    );
}
