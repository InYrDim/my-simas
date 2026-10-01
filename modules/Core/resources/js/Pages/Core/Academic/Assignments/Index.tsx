import { useState } from 'react';

import { DataTable, EmptyState, OptionSelect } from '@shared/components/page-parts';
import { Button } from '@shared/components/ui/button';
import { TableCell, TableRow } from '@shared/components/ui/table';

import MasterPage from '../../../../Components/MasterPage';
import type {
    Assignment,
    ClassGroup,
    SchoolSummary,
    Teacher,
} from '../../../../types/master';

/**
 * Pengampu Mapel: assign a teacher to each subject of one class. Works on
 * base data (subjects, classes, teachers) — it owns no records of its own
 * beyond the assignment.
 */
export default function AssignmentsIndex({
    school,
    assignments,
    classes,
    teachers,
}: {
    school: SchoolSummary;
    assignments: Assignment[];
    classes: ClassGroup[];
    teachers: Teacher[];
}) {
    const assigned = classes.filter((group) =>
        assignments.some((row) => row.class === group.name),
    );
    const [className, setClassName] = useState(assigned[0]?.name ?? '');
    const [changes, setChanges] = useState<Record<string, string>>({});

    const rows = assignments.filter((row) => row.class === className);
    const teacherOptions = teachers.map((teacher) => ({
        value: teacher.name,
        label: teacher.name,
    }));
    const dirty = Object.keys(changes).length;

    return (
        <MasterPage
            school={school}
            title="Pengampu Mapel"
            description="Tentukan guru pengampu tiap mata pelajaran di sebuah kelas."
            width="max-w-5xl"
            actions={
                <Button disabled={dirty === 0} onClick={() => setChanges({})}>
                    Simpan{dirty > 0 ? ` (${dirty} perubahan)` : ''}
                </Button>
            }
        >
            <div className="mb-4 max-w-xs">
                <OptionSelect
                    label="Kelas"
                    value={className}
                    onChange={(value) => {
                        setClassName(value);
                        setChanges({});
                    }}
                    options={assigned.map((group) => ({
                        value: group.name,
                        label: `Kelas ${group.name}`,
                    }))}
                />
            </div>

            {rows.length === 0 ? (
                <EmptyState>Belum ada mata pelajaran untuk kelas ini.</EmptyState>
            ) : (
                <DataTable head={['Mata pelajaran', 'Guru pengampu', 'JP/minggu']}>
                    {rows.map((row) => (
                        <TableRow key={row.subject}>
                            <TableCell className="font-medium">{row.subject}</TableCell>
                            <TableCell className="w-80">
                                <OptionSelect
                                    label={`Guru ${row.subject}`}
                                    value={changes[row.subject] ?? row.teacher}
                                    onChange={(value) =>
                                        setChanges((current) => ({
                                            ...current,
                                            [row.subject]: value,
                                        }))
                                    }
                                    options={teacherOptions}
                                />
                            </TableCell>
                            <TableCell>{row.hours}</TableCell>
                        </TableRow>
                    ))}
                </DataTable>
            )}
        </MasterPage>
    );
}
