import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

import { assignments as assignmentsPage } from '@/actions/Modules/Core/App/Http/Controllers/MasterDataController';
import { DataTable } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type { SchoolSummary, Subject } from '../../../../types/master';

/**
 * Mata Pelajaran: the subject catalogue only. Who teaches what is an
 * action on this data and lives under Akademik › Pengampu Mapel.
 */
export default function SubjectsIndex({
    school,
    subjects,
}: {
    school: SchoolSummary;
    subjects: Subject[];
}) {
    return (
        <MasterPage
            school={school}
            title="Mata Pelajaran"
            description="Daftar mata pelajaran yang diajarkan di sekolah."
            actions={
                <>
                    <Button asChild variant="outline">
                        <Link href={assignmentsPage.url()}>Atur pengampu</Link>
                    </Button>
                    <FormDialog
                        title="Tambah mata pelajaran"
                        trigger={
                            <Button>
                                <PlusIcon />
                                Tambah mata pelajaran
                            </Button>
                        }
                    >
                        <InputField label="Kode" id="code" placeholder="MTK" />
                        <InputField label="Nama" id="name" />
                        <SelectField
                            label="Kelompok"
                            id="group"
                            options={['Umum', 'Peminatan', 'Muatan Lokal', 'Kejuruan']}
                        />
                        <InputField label="KKM" id="kkm" type="number" defaultValue="75" />
                    </FormDialog>
                </>
            }
        >
            <DataTable head={['Kode', 'Nama', 'Kelompok', 'Tingkat', 'KKM']}>
                {subjects.map((subject) => (
                    <TableRow key={subject.id}>
                        <TableCell className="font-mono text-xs">{subject.code}</TableCell>
                        <TableCell className="font-medium">{subject.name}</TableCell>
                        <TableCell>
                            <Badge variant="secondary">{subject.group}</Badge>
                        </TableCell>
                        <TableCell>{subject.grades}</TableCell>
                        <TableCell>{subject.kkm}</TableCell>
                    </TableRow>
                ))}
            </DataTable>
        </MasterPage>
    );
}
