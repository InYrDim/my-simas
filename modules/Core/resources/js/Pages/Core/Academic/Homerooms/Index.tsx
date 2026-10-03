import { useForm } from '@inertiajs/react';

import { update } from '@/actions/Modules/Core/App/Http/Controllers/HomeroomController';
import {
    DataTable,
    EmptyState,
    OptionSelect,
} from '@shared/components/page-parts';
import { Alert, AlertDescription } from '@shared/components/ui/alert';
import { Badge } from '@shared/components/ui/badge';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import MasterPage from '../../../../Components/MasterPage';
import type {
    ClassGroup,
    SchoolSummary,
    Teacher,
} from '../../../../types/master';

const NONE = 'none';

/**
 * Wali Kelas: set the homeroom teacher of every rombel in one sweep. A
 * teacher holding more than one class is flagged, not blocked.
 */
export default function HomeroomsIndex({
    school,
    classes,
    teachers,
}: {
    school: SchoolSummary;
    classes: ClassGroup[];
    teachers: Teacher[];
}) {
    const form = useForm<{ homerooms: Record<number, string> }>({
        homerooms: Object.fromEntries(
            classes.map((group) => [
                group.id,
                String(group.homeroomId ?? NONE),
            ]),
        ),
    });
    const chosen = form.data.homerooms;

    const options = [
        { value: NONE, label: 'Belum ditentukan' },
        ...teachers.map((teacher) => ({
            value: String(teacher.id),
            label: teacher.name,
        })),
    ];
    const load = (teacherId: string) =>
        teacherId === NONE
            ? 0
            : Object.values(chosen).filter((value) => value === teacherId)
                  .length;

    return (
        <MasterPage
            school={school}
            title={school.homeroomLabel}
            description={`Tetapkan ${school.homeroomLabel.toLowerCase()} untuk tiap rombel tahun ajaran aktif.`}
            width="max-w-5xl"
            mock={false}
            writePermission="core.academic.manage"
            actions={
                <Button
                    disabled={!form.isDirty || form.processing}
                    onClick={() =>
                        form.put(update.url(), {
                            preserveScroll: true,
                            onSuccess: () => form.setDefaults(),
                        })
                    }
                >
                    Simpan penetapan
                </Button>
            }
        >
            {form.errors.homerooms !== undefined && (
                <Alert variant="destructive" className="mb-6">
                    <AlertDescription>{form.errors.homerooms}</AlertDescription>
                </Alert>
            )}

            {classes.length === 0 ? (
                <EmptyState>
                    Belum ada kelas pada tahun ajaran aktif. Aktifkan tahun
                    ajaran dan buat kelasnya di Master Data.
                </EmptyState>
            ) : (
                <DataTable
                    head={['Kelas', 'Ruangan', school.homeroomLabel, '']}
                >
                    {classes.map((group) => (
                        <TableRow key={group.id}>
                            <TableCell className="font-medium">
                                {group.name}
                            </TableCell>
                            <TableCell>{group.room ?? '—'}</TableCell>
                            <TableCell className="w-80">
                                <OptionSelect
                                    label={`${school.homeroomLabel} ${group.name}`}
                                    value={chosen[group.id]}
                                    onChange={(value) =>
                                        form.setData('homerooms', {
                                            ...chosen,
                                            [group.id]: value,
                                        })
                                    }
                                    options={options}
                                />
                            </TableCell>
                            <TableCell>
                                {load(chosen[group.id]) > 1 && (
                                    <Badge variant="outline">
                                        merangkap {load(chosen[group.id])} kelas
                                    </Badge>
                                )}
                            </TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </MasterPage>
    );
}
