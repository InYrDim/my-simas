import { PlusIcon } from 'lucide-react';

import { DataTable, EmptyState } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@shared/components/ui/tabs';

import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type { Grade, Major, SchoolSummary } from '../../../../types/master';

/**
 * Tingkat & Jurusan: grade levels follow the school's jenjang; the Jurusan
 * tab exists only for SMA (peminatan) and SMK (kompetensi keahlian).
 */
export default function GradesIndex({
    school,
    grades,
    majors,
}: {
    school: SchoolSummary;
    grades: Grade[];
    majors: Major[];
}) {
    const majorLabel = school.level === 'smk' ? 'Kompetensi keahlian' : 'Peminatan';

    return (
        <MasterPage
            school={school}
            title={school.hasMajors ? 'Tingkat & Jurusan' : 'Tingkat'}
            description={`Tingkat kelas untuk jenjang ${school.levelLabel}.`}
            width="max-w-5xl"
        >
            <Tabs defaultValue="grades" key={school.level}>
                <TabsList>
                    <TabsTrigger value="grades">Tingkat</TabsTrigger>
                    {school.hasMajors && (
                        <TabsTrigger value="majors">{majorLabel}</TabsTrigger>
                    )}
                </TabsList>

                <TabsContent value="grades" className="mt-6">
                    <DataTable head={['Tingkat', 'Urutan', 'Jumlah rombel']}>
                        {grades.map((grade) => (
                            <TableRow key={grade.id}>
                                <TableCell className="font-medium">
                                    Kelas {grade.name}
                                </TableCell>
                                <TableCell>{grade.order}</TableCell>
                                <TableCell>{grade.classes}</TableCell>
                            </TableRow>
                        ))}
                    </DataTable>
                </TabsContent>

                {school.hasMajors && (
                    <TabsContent value="majors" className="mt-6">
                        <div className="mb-4 flex justify-end">
                            <FormDialog
                                title={`Tambah ${majorLabel.toLowerCase()}`}
                                trigger={
                                    <Button>
                                        <PlusIcon />
                                        Tambah {majorLabel.toLowerCase()}
                                    </Button>
                                }
                            >
                                <InputField label="Kode" id="code" placeholder="IPA" />
                                <InputField label="Nama" id="name" />
                                <SelectField
                                    label="Jenis"
                                    id="kind"
                                    options={['Peminatan', 'Kompetensi Keahlian']}
                                    defaultValue={majors[0]?.kind}
                                />
                            </FormDialog>
                        </div>

                        {majors.length === 0 ? (
                            <EmptyState>Belum ada data.</EmptyState>
                        ) : (
                            <DataTable
                                head={['Kode', 'Nama', 'Jenis', 'Konsentrasi']}
                            >
                                {majors.map((major) => (
                                    <TableRow key={major.id}>
                                        <TableCell className="font-medium">
                                            {major.code}
                                        </TableCell>
                                        <TableCell>{major.name}</TableCell>
                                        <TableCell>{major.kind}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {major.concentrations.length === 0
                                                ? '—'
                                                : major.concentrations.join(', ')}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </DataTable>
                        )}
                    </TabsContent>
                )}
            </Tabs>
        </MasterPage>
    );
}
