import { PlusIcon } from 'lucide-react';

import {
    destroy,
    store,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/MajorController';
import { DataTable, EmptyState } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@shared/components/ui/tabs';

import ConfirmAction from '../../../../Components/ConfirmAction';
import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type { Grade, Major, SchoolSummary } from '../../../../types/master';

function MajorForm({ major, kind }: { major?: Major; kind: string }) {
    return (
        <>
            <InputField
                label="Kode"
                id="code"
                placeholder="IPA"
                defaultValue={major?.code}
            />
            <InputField label="Nama" id="name" defaultValue={major?.name} />
            <SelectField
                label="Jenis"
                id="kind"
                options={['Peminatan', 'Kompetensi Keahlian']}
                defaultValue={major?.kind ?? kind}
            />
            <InputField
                label="Konsentrasi"
                id="concentrations"
                hint="Pisahkan dengan koma. Boleh dikosongkan."
                defaultValue={major?.concentrations.join(', ')}
            />
        </>
    );
}

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
    const isVocational = school.level === 'smk';
    const majorLabel = isVocational ? 'Kompetensi keahlian' : 'Peminatan';
    const defaultKind = isVocational ? 'Kompetensi Keahlian' : 'Peminatan';

    return (
        <MasterPage
            school={school}
            title={school.hasMajors ? 'Tingkat & Jurusan' : 'Tingkat'}
            description={`Tingkat kelas untuk jenjang ${school.levelLabel}.`}
            width="max-w-5xl"
            mock={false}
        >
            <Tabs defaultValue="grades">
                <TabsList>
                    <TabsTrigger value="grades">Tingkat</TabsTrigger>
                    {school.hasMajors && (
                        <TabsTrigger value="majors">{majorLabel}</TabsTrigger>
                    )}
                </TabsList>

                <TabsContent value="grades" className="mt-6">
                    <DataTable
                        head={[
                            'Tingkat',
                            'Urutan',
                            'Rombel tahun ajaran aktif',
                        ]}
                    >
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
                                route={store()}
                                title={`Tambah ${majorLabel.toLowerCase()}`}
                                trigger={
                                    <Button>
                                        <PlusIcon />
                                        Tambah {majorLabel.toLowerCase()}
                                    </Button>
                                }
                            >
                                <MajorForm kind={defaultKind} />
                            </FormDialog>
                        </div>

                        {majors.length === 0 ? (
                            <EmptyState>Belum ada data.</EmptyState>
                        ) : (
                            <DataTable
                                head={[
                                    'Kode',
                                    'Nama',
                                    'Jenis',
                                    'Konsentrasi',
                                    '',
                                ]}
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
                                                : major.concentrations.join(
                                                      ', ',
                                                  )}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-2">
                                                <FormDialog
                                                    route={update(major.id)}
                                                    title={`Ubah ${major.code}`}
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            Ubah
                                                        </Button>
                                                    }
                                                >
                                                    <MajorForm
                                                        major={major}
                                                        kind={defaultKind}
                                                    />
                                                </FormDialog>
                                                <ConfirmAction
                                                    route={destroy(major.id)}
                                                    title={`Hapus ${major.code}?`}
                                                    description="Jurusan yang masih dipakai kelas tidak bisa dihapus."
                                                    confirmLabel="Hapus"
                                                    trigger={
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                        >
                                                            Hapus
                                                        </Button>
                                                    }
                                                />
                                            </div>
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
