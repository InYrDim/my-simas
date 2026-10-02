import { Link } from '@inertiajs/react';
import { PlusIcon } from 'lucide-react';

import {
    destroy,
    store,
    update,
} from '@/actions/Modules/Core/App/Http/Controllers/SubjectController';
import { index as assignmentsPage } from '@/actions/Modules/Core/App/Http/Controllers/TeachingAssignmentController';
import { DataTable, EmptyState } from '@shared/components/page-parts';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import ConfirmAction from '../../../../Components/ConfirmAction';
import FormDialog from '../../../../Components/FormDialog';
import { InputField, SelectField } from '../../../../Components/FormField';
import MasterPage from '../../../../Components/MasterPage';
import type { SchoolSummary, Subject } from '../../../../types/master';

function SubjectForm({ subject }: { subject?: Subject }) {
    return (
        <>
            <InputField
                label="Kode"
                id="code"
                placeholder="MTK"
                defaultValue={subject?.code}
            />
            <InputField label="Nama" id="name" defaultValue={subject?.name} />
            <SelectField
                label="Kelompok"
                id="group"
                options={['Umum', 'Peminatan', 'Muatan Lokal', 'Kejuruan']}
                defaultValue={subject?.group}
            />
            <InputField
                label="KKM"
                id="kkm"
                type="number"
                defaultValue={subject?.kkm ?? 75}
            />
        </>
    );
}

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
            mock={false}
            actions={
                <>
                    <Button asChild variant="outline">
                        <Link href={assignmentsPage.url()}>Atur pengampu</Link>
                    </Button>
                    <FormDialog
                        route={store()}
                        title="Tambah mata pelajaran"
                        trigger={
                            <Button>
                                <PlusIcon />
                                Tambah mata pelajaran
                            </Button>
                        }
                    >
                        <SubjectForm />
                    </FormDialog>
                </>
            }
        >
            {subjects.length === 0 ? (
                <EmptyState>Belum ada mata pelajaran.</EmptyState>
            ) : (
                <DataTable head={['Kode', 'Nama', 'Kelompok', 'Tingkat', 'KKM', '']}>
                    {subjects.map((subject) => (
                        <TableRow key={subject.id}>
                            <TableCell className="font-mono text-xs">{subject.code}</TableCell>
                            <TableCell className="font-medium">{subject.name}</TableCell>
                            <TableCell>
                                <Badge variant="secondary">{subject.group}</Badge>
                            </TableCell>
                            <TableCell>{subject.grades}</TableCell>
                            <TableCell>{subject.kkm}</TableCell>
                            <TableCell>
                                <div className="flex justify-end gap-2">
                                    <FormDialog
                                        route={update(subject.id)}
                                        title={`Ubah ${subject.name}`}
                                        trigger={
                                            <Button variant="ghost" size="sm">
                                                Ubah
                                            </Button>
                                        }
                                    >
                                        <SubjectForm subject={subject} />
                                    </FormDialog>
                                    <ConfirmAction
                                        route={destroy(subject.id)}
                                        title={`Hapus ${subject.name}?`}
                                        description="Mata pelajaran akan dihapus dari daftar."
                                        confirmLabel="Hapus"
                                        trigger={
                                            <Button variant="ghost" size="sm">
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
        </MasterPage>
    );
}
